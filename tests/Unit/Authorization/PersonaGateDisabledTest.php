<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use Grazulex\LaravelMultiPersona\Models\Persona;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PersonaGateDisabledTest extends TestCase
{
    public function test_the_gate_ignores_personas_when_the_integration_is_disabled(): void
    {
        $user = (new Authenticatable)->forceFill(['id' => 1]);
        $this->actingAs($user);

        Persona::create([
            'user_id' => 1,
            'name' => 'Admin',
            'context' => ['permissions' => ['manage_users']],
            'is_active' => true,
        ]);

        $this->assertFalse($user->can('manage_users'));
        $this->assertFalse(Gate::allows('manage_users'));
    }
}
