<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Traits;

use Grazulex\LaravelMultiPersona\Models\Persona;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasPersonas
{
    /**
     * Get all personas for this user
     */
    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'user_id');
    }

    /**
     * Get the active persona for this user
     */
    public function activePersona(): ?Persona
    {
        return $this->personas()->where('is_active', true)->first();
    }

    /**
     * Create a new persona for this user
     */
    public function createPersona(string $name, array $context = []): Persona
    {
        return $this->personas()->create([
            'name' => $name,
            'context' => $context,
            'is_active' => false,
        ]);
    }

    /**
     * Switch to a specific persona
     */
    public function switchToPersona(Persona|int|string $persona): bool
    {
        if (is_scalar($persona)) {
            $persona = $this->personas()->find($persona);
        }

        if (! $persona) {
            return false;
        }

        // Deactivate all other personas
        $this->personas()->update(['is_active' => false]);

        // Activate the selected persona
        $persona->activate();

        // Also set it as active in the PersonaManager for consistency
        app('multipersona')->setActive($persona);

        return true;
    }

    /**
     * Check if user has a specific persona
     */
    public function hasPersona(string $name): bool
    {
        return $this->personas()->where('name', $name)->exists();
    }

    /**
     * Get persona by name
     */
    public function getPersonaByName(string $name): ?Persona
    {
        return $this->personas()->where('name', $name)->first();
    }
}
