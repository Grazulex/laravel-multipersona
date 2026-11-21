<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use Grazulex\LaravelMultiPersona\Authorization\PersonaGate;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonaGateTest extends TestCase
{
    public function test_an_ability_listed_in_the_active_persona_is_granted(): void
    {
        $user = $this->actingAsUser(1);
        $this->createPersona(1, 'Admin', ['permissions' => ['manage_users']], true);

        $this->assertTrue($user->can('manage_users'));
        $this->assertTrue(Gate::allows('manage_users'));
    }

    public function test_the_result_follows_the_active_persona(): void
    {
        // The bundled listeners restore the user from the database
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('users')->insert(['id' => 1]);

        $user = $this->actingAsUser(1);
        $user->exists = true;
        $admin = $this->createPersona(1, 'Admin', ['permissions' => ['manage_users']]);
        $client = $this->createPersona(1, 'Client', ['permissions' => ['view_dashboard']]);
        $manager = app(PersonaManager::class);

        $manager->setActive($admin);
        $this->assertTrue($user->can('manage_users'));
        $this->assertFalse($user->can('view_dashboard'));

        $manager->setActive($client);
        $this->assertFalse($user->can('manage_users'));
        $this->assertTrue($user->can('view_dashboard'));
    }

    public function test_unlisted_abilities_are_left_to_the_regular_gates_by_default(): void
    {
        $user = $this->actingAsUser(1);
        $this->createPersona(1, 'Client', ['permissions' => ['view_dashboard']], true);
        Gate::define('edit_profile', static fn (): bool => true);

        $this->assertTrue($user->can('edit_profile'));
        $this->assertFalse($user->can('manage_users'));
    }

    public function test_unlisted_abilities_are_denied_when_deny_unlisted_is_enabled(): void
    {
        config()->set('multipersona.gate.deny_unlisted', true);

        $user = $this->actingAsUser(1);
        $this->createPersona(1, 'Client', ['permissions' => ['view_dashboard']], true);
        Gate::define('edit_profile', static fn (): bool => true);

        $this->assertFalse($user->can('edit_profile'));
        $this->assertTrue($user->can('view_dashboard'));
    }

    public function test_a_persona_without_permissions_does_not_change_the_decision(): void
    {
        config()->set('multipersona.gate.deny_unlisted', true);

        $user = $this->actingAsUser(1);
        $this->createPersona(1, 'Plain', ['role' => 'user'], true);
        Gate::define('edit_profile', static fn (): bool => true);

        $this->assertTrue($user->can('edit_profile'));
        $this->assertFalse($user->can('manage_users'));
    }

    public function test_a_user_without_active_persona_is_not_affected(): void
    {
        config()->set('multipersona.gate.deny_unlisted', true);

        $user = $this->actingAsUser(1);
        $this->createPersona(1, 'Admin', ['permissions' => ['manage_users']]);
        Gate::define('edit_profile', static fn (): bool => true);

        $this->assertFalse($user->can('manage_users'));
        $this->assertTrue($user->can('edit_profile'));
    }

    public function test_the_active_persona_of_the_checked_user_is_used(): void
    {
        $this->actingAsUser(1);
        $this->createPersona(1, 'Admin', ['permissions' => ['manage_users']], true);
        $this->createPersona(2, 'Client', ['permissions' => ['view_dashboard']], true);

        $other = (new Authenticatable)->forceFill(['id' => 2]);

        $this->assertFalse(Gate::forUser($other)->allows('manage_users'));
        $this->assertTrue(Gate::forUser($other)->allows('view_dashboard'));
        $this->assertTrue(Gate::allows('manage_users'));
    }

    public function test_guests_are_not_affected(): void
    {
        $this->createPersona(1, 'Admin', ['permissions' => ['manage_users']], true);

        $this->assertNull(app(PersonaGate::class)->before(null, 'manage_users'));
        $this->assertFalse(Gate::allows('manage_users'));
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('multipersona.gate.enabled', true);
    }

    private function actingAsUser(int $id): Authenticatable
    {
        $user = (new Authenticatable)->forceFill(['id' => $id]);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createPersona(int $userId, string $name, array $context, bool $active = false): Persona
    {
        return Persona::create([
            'user_id' => $userId,
            'name' => $name,
            'context' => $context,
            'is_active' => $active,
        ]);
    }
}
