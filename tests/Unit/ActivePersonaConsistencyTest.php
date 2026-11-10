<?php

declare(strict_types=1);

namespace Tests\Unit;

use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Grazulex\LaravelMultiPersona\Traits\HasPersonas;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class ActivePersonaConsistencyTest extends TestCase
{
    private PersonaManager $personaManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personaManager = app(PersonaManager::class);
    }

    public function test_activate_deactivates_the_other_personas_of_the_same_user(): void
    {
        $first = $this->createPersona(1, 'First', true);
        $second = $this->createPersona(1, 'Second');
        $otherUsers = $this->createPersona(2, 'Other user', true);

        $second->activate();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertTrue($otherUsers->fresh()->is_active);
    }

    public function test_set_active_persists_the_active_persona(): void
    {
        $first = $this->createPersona(1, 'First', true);
        $second = $this->createPersona(1, 'Second');

        $this->personaManager->setActive($second);

        $this->assertTrue($second->fresh()->is_active);
        $this->assertFalse($first->fresh()->is_active);
        $this->assertSame($second->id, Session::get('active_persona_id'));
    }

    public function test_set_active_by_id_persists_the_active_persona(): void
    {
        $persona = $this->createPersona(1, 'First');

        $this->personaManager->setActive($persona->id);

        $this->assertTrue($persona->fresh()->is_active);
    }

    public function test_manager_and_trait_agree_on_the_active_persona(): void
    {
        $user = $this->createUser(1);
        $first = $user->createPersona('First');
        $second = $user->createPersona('Second');

        $this->personaManager->setActive($first);
        $this->assertSame($first->id, $user->activePersona()?->id);
        $this->assertSame($first->id, $this->personaManager->id());

        $user->switchToPersona($second);
        $this->assertSame($second->id, $user->activePersona()?->id);
        $this->assertSame($second->id, $this->personaManager->id());
        $this->assertFalse($first->fresh()->is_active);
        $this->assertSame(1, $user->personas()->where('is_active', true)->count());
    }

    public function test_clear_deactivates_the_persona_in_the_database(): void
    {
        $user = $this->createUser(1);
        $persona = $user->createPersona('First');

        $this->personaManager->setActive($persona);
        $this->personaManager->clear();

        $this->assertFalse($persona->fresh()->is_active);
        $this->assertNull($user->activePersona());
        $this->assertNull($this->personaManager->current());
        $this->assertFalse(Session::has('active_persona_id'));
    }

    public function test_current_follows_an_activation_made_outside_the_manager(): void
    {
        $first = $this->createPersona(1, 'First');
        $second = $this->createPersona(1, 'Second');

        $this->personaManager->setActive($first);

        // Another persona of the same user is activated elsewhere
        $second->activate();

        // A fresh manager (next request) must follow the database
        $manager = new PersonaManager;

        $this->assertSame($second->id, $manager->id());
        $this->assertSame($second->id, Session::get('active_persona_id'));
    }

    public function test_current_drops_a_session_pointer_to_a_deactivated_persona(): void
    {
        $persona = $this->createPersona(1, 'First');

        $this->personaManager->setActive($persona);
        $persona->deactivate();

        $manager = new PersonaManager;

        $this->assertNull($manager->current());
        $this->assertFalse(Session::has('active_persona_id'));
    }

    public function test_current_restores_the_active_persona_of_the_authenticated_user(): void
    {
        $user = (new Authenticatable)->forceFill(['id' => 1]);
        $this->actingAs($user);

        $this->createPersona(1, 'Inactive');
        $active = $this->createPersona(1, 'Active', true);
        $this->createPersona(2, 'Other user', true);

        $this->assertFalse(Session::has('active_persona_id'));
        $this->assertSame($active->id, $this->personaManager->id());
        $this->assertSame($active->id, Session::get('active_persona_id'));
    }

    private function createPersona(int $userId, string $name, bool $active = false): Persona
    {
        return Persona::create([
            'user_id' => $userId,
            'name' => $name,
            'context' => [],
            'is_active' => $active,
        ]);
    }

    private function createUser(int $id): object
    {
        return new class($id) extends \Illuminate\Database\Eloquent\Model
        {
            use HasPersonas;

            protected $table = 'users';

            protected $fillable = ['name', 'email'];

            public $timestamps = false;

            public function __construct(int $id = 1)
            {
                parent::__construct();
                $this->id = $id;
            }
        };
    }
}
