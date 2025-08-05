<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Database\Factories;

use Grazulex\LaravelMultiPersona\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Admin', 'User', 'Manager', 'Editor']),
            'context' => [
                'role' => $this->faker->randomElement(['admin', 'user', 'manager', 'editor']),
                'permissions' => $this->faker->randomElements(['read', 'write', 'delete', 'create'], 2),
            ],
            'is_active' => false,
            'user_id' => 1, // Will be overridden in tests
            'user_type' => 'App\Models\User',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin',
            'context' => [
                'role' => 'admin',
                'permissions' => ['read', 'write', 'delete', 'create'],
            ],
        ]);
    }

    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'User',
            'context' => [
                'role' => 'user',
                'permissions' => ['read'],
            ],
        ]);
    }
}
