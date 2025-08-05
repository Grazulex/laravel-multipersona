<?php

declare(strict_types=1);

namespace Tests\Unit;

use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Support\Collection;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    private PersonaManager $personaManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personaManager = app(PersonaManager::class);
    }

    public function test_persona_helper_returns_current_persona(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->id,
            'name' => 'Test Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        // Set active persona
        $this->personaManager->setActive($persona);

        // Test helper function
        $currentPersona = persona();

        $this->assertNotNull($currentPersona);
        $this->assertEquals($persona->id, $currentPersona->getId());
        $this->assertEquals('Test Persona', $currentPersona->getName());
    }

    public function test_persona_helper_returns_null_when_no_active_persona(): void
    {
        // Clear any active persona
        $this->personaManager->clear();

        // Test helper function
        $currentPersona = persona();

        $this->assertNull($currentPersona);
    }

    public function test_personas_helper_returns_user_personas(): void
    {
        // Create a user and multiple personas
        $user = $this->createUser();

        $persona1 = Persona::create([
            'user_id' => $user->id,
            'name' => 'Admin Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        $persona2 = Persona::create([
            'user_id' => $user->id,
            'name' => 'User Persona',
            'context' => ['role' => 'user'],
            'is_active' => false,
        ]);

        // Test helper function
        $userPersonas = personas($user);

        $this->assertInstanceOf(Collection::class, $userPersonas);
        $this->assertCount(2, $userPersonas);

        $personaIds = $userPersonas->pluck('id')->toArray();
        $this->assertContains($persona1->id, $personaIds);
        $this->assertContains($persona2->id, $personaIds);
    }

    public function test_personas_helper_returns_empty_collection_for_user_without_personas(): void
    {
        // Create a user without personas
        $user = $this->createUser();

        // Test helper function
        $userPersonas = personas($user);

        $this->assertInstanceOf(Collection::class, $userPersonas);
        $this->assertCount(0, $userPersonas);
    }

    public function test_personas_helper_with_null_user(): void
    {
        // Test helper function with null user
        $userPersonas = personas(null);

        $this->assertInstanceOf(Collection::class, $userPersonas);
        $this->assertCount(0, $userPersonas);
    }

    public function test_helpers_are_globally_available(): void
    {
        // Ensure helper functions are available globally
        $this->assertTrue(function_exists('persona'));
        $this->assertTrue(function_exists('personas'));
    }

    public function test_persona_helper_method_chaining(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->id,
            'name' => 'Test Persona',
            'context' => ['role' => 'admin', 'permissions' => ['read', 'write']],
            'is_active' => true,
        ]);

        // Set active persona
        $this->personaManager->setActive($persona);

        // Test method chaining through helper
        $personaId = persona()?->getId();
        $personaName = persona()?->getName();
        $context = persona()?->getContext();

        $this->assertEquals($persona->id, $personaId);
        $this->assertEquals('Test Persona', $personaName);
        $this->assertEquals(['role' => 'admin', 'permissions' => ['read', 'write']], $context);
    }

    private function createUser(): object
    {
        // Create a simple User-like model for testing
        return new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';

            protected $fillable = ['name', 'email'];

            public $timestamps = false;

            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
                $this->id = 1;
                $this->name = 'Test User';
                $this->email = 'test@example.com';
            }
        };
    }
}
