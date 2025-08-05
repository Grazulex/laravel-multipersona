<?php

declare(strict_types=1);

namespace Tests\Integration;

use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Grazulex\LaravelMultiPersona\Traits\HasPersonas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;
use Tests\TestCase;

class FullWorkflowTest extends TestCase
{
    private PersonaManager $personaManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personaManager = app(PersonaManager::class);
        Event::fake();
    }

    public function test_complete_persona_workflow(): void
    {
        // Step 1: Create a user with HasPersonas trait
        $user = $this->createUserWithTrait();

        // Step 2: Create multiple personas for the user
        $adminPersona = $user->createPersona('Company Admin', [
            'role' => 'admin',
            'company_id' => 123,
            'permissions' => ['read', 'write', 'delete', 'manage_users'],
        ]);

        $userPersona = $user->createPersona('Regular User', [
            'role' => 'user',
            'company_id' => 123,
            'permissions' => ['read', 'write'],
        ]);

        $clientPersona = $user->createPersona('Client View', [
            'role' => 'client',
            'company_id' => 456,
            'permissions' => ['read'],
        ]);

        // Verify personas were created
        $this->assertCount(3, $user->personas);
        $this->assertEquals('Company Admin', $adminPersona->getName());
        $this->assertEquals('Regular User', $userPersona->getName());
        $this->assertEquals('Client View', $clientPersona->getName());

        // Step 3: Switch to admin persona using trait method
        $user->switchToPersona($adminPersona);

        // The trait method only updates the database, we need to also set it in the manager
        $this->personaManager->setActive($adminPersona);

        // Verify persona is active
        $current = $this->personaManager->current();
        $this->assertNotNull($current);
        $this->assertEquals($adminPersona->getId(), $current->getId());
        $this->assertEquals('admin', $this->personaManager->context()['role']);

        // Verify events were fired
        Event::assertDispatched(PersonaActivated::class);

        // Step 4: Switch to user persona using manager
        Event::clearResolvedInstances();
        $this->personaManager->setActive($userPersona);

        // Verify switch
        $current = $this->personaManager->current();
        $this->assertEquals($userPersona->getId(), $current->getId());
        $this->assertEquals('user', $this->personaManager->context()['role']);

        // Verify switch event
        Event::assertDispatched(PersonaSwitched::class, function ($event) use ($adminPersona, $userPersona) {
            return $event->getPersona()->getId() === $userPersona->getId()
                && $event->getPreviousPersona()?->getId() === $adminPersona->getId();
        });

        // Step 5: Test permissions through context
        $context = $this->personaManager->context();
        $this->assertContains('read', $context['permissions']);
        $this->assertContains('write', $context['permissions']);
        $this->assertNotContains('delete', $context['permissions']);

        // Step 6: Switch to client persona with limited permissions
        $this->personaManager->setActive($clientPersona);

        $context = $this->personaManager->context();
        $this->assertEquals('client', $context['role']);
        $this->assertEquals(456, $context['company_id']);
        $this->assertEquals(['read'], $context['permissions']);

        // Step 7: Test helper functions
        $this->assertEquals($clientPersona->getId(), persona()?->getId());

        $allPersonas = personas($user);
        $this->assertCount(3, $allPersonas);

        // Step 8: Clear active persona
        Event::clearResolvedInstances();
        $this->personaManager->clear();

        // Verify cleared
        $this->assertNull($this->personaManager->current());
        $this->assertNull(persona());

        // Verify deactivation event
        Event::assertDispatched(PersonaDeactivated::class);
    }

    public function test_persona_validation_workflow(): void
    {
        $user = $this->createUserWithTrait();
        $anotherUser = $this->createUserWithTrait();

        // Create persona for first user
        $persona = $user->createPersona('Admin Persona', [
            'role' => 'admin',
            'permissions' => ['manage_all'],
        ]);

        // Test that persona belongs to correct user
        $this->assertTrue($this->personaManager->canActivate($persona, $user));
        $this->assertFalse($this->personaManager->canActivate($persona, $anotherUser));

        // Test switching with wrong user
        $result = $this->personaManager->switchTo($persona, $anotherUser);
        $this->assertFalse($result);
        $this->assertNull($this->personaManager->current());

        // Test switching with correct user
        $result = $this->personaManager->switchTo($persona, $user);
        $this->assertTrue($result);
        $this->assertEquals($persona->getId(), $this->personaManager->current()?->getId());
    }

    public function test_persona_persistence_across_requests(): void
    {
        $user = $this->createUserWithTrait();

        $persona = $user->createPersona('Persistent Persona', [
            'role' => 'manager',
            'data' => ['test' => 'value'],
        ]);

        // Set active persona
        $this->personaManager->setActive($persona);

        // Simulate new request by creating new manager instance
        $newManager = new PersonaManager();

        // Should retrieve from session
        $retrieved = $newManager->current();
        $this->assertNotNull($retrieved);
        $this->assertEquals($persona->getId(), $retrieved->getId());
        $this->assertEquals('manager', $newManager->context()['role']);
        $this->assertEquals('value', $newManager->context()['data']['test']);
    }

    public function test_persona_context_access_methods(): void
    {
        $user = $this->createUserWithTrait();

        $persona = $user->createPersona('Complex Persona', [
            'role' => 'admin',
            'department' => 'IT',
            'permissions' => ['read', 'write', 'admin', 'admin_panel'],
            'metadata' => [
                'level' => 5,
                'certified' => true,
                'tags' => ['senior', 'lead'],
            ],
        ]);

        $this->personaManager->setActive($persona);

        // Test various access methods
        $this->assertTrue($this->personaManager->hasActive());
        $this->assertEquals($persona->getId(), $this->personaManager->id());
        $this->assertEquals('Complex Persona', $this->personaManager->name());

        $context = $this->personaManager->context();
        $this->assertEquals('admin', $context['role']);
        $this->assertEquals('IT', $context['department']);
        $this->assertEquals(5, $context['metadata']['level']);
        $this->assertTrue($context['metadata']['certified']);
        $this->assertContains('senior', $context['metadata']['tags']);

        // Test canAccess method through interface
        $this->assertTrue($persona->canAccess('admin_panel'));
        $this->assertTrue($persona->isActive());
    }

    public function test_error_handling_and_edge_cases(): void
    {
        // Test with non-existent persona ID
        $this->expectException(InvalidArgumentException::class);
        $this->personaManager->setActive(999999);
    }

    public function test_helper_functions_integration(): void
    {
        $user = $this->createUserWithTrait();

        $persona1 = $user->createPersona('Helper Test 1', ['role' => 'user']);
        $persona2 = $user->createPersona('Helper Test 2', ['role' => 'admin']);

        // Test personas() helper
        $allPersonas = personas($user);
        $this->assertCount(2, $allPersonas);

        // Test persona() helper when none active
        $this->assertNull(persona());

        // Set active and test again
        $this->personaManager->setActive($persona1);
        $this->assertEquals($persona1->getId(), persona()?->getId());

        // Test helper method chaining
        $personaName = persona()?->getName();
        $personaContext = persona()?->getContext();

        $this->assertEquals('Helper Test 1', $personaName);
        $this->assertEquals('user', $personaContext['role']);
    }

    private function createUserWithTrait(): object
    {
        return new class extends Model
        {
            use HasPersonas;

            protected $table = 'users';

            protected $fillable = ['name', 'email'];

            public $timestamps = false;

            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
                static $counter = 1;
                $this->id = $counter++;
                $this->name = 'Test User '.$this->id;
                $this->email = 'test'.$this->id.'@example.com';
            }
        };
    }
}
