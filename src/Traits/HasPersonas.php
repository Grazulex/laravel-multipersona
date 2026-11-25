<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Traits;

use DateInterval;
use DateTimeInterface;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Models\PersonaSwitchLog;
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
     * Get the persona switches recorded for this user (requires the audit,
     * see `multipersona.audit`)
     */
    public function personaSwitchLogs(): HasMany
    {
        return $this->hasMany(PersonaSwitchLog::class, 'user_id');
    }

    /**
     * Get the active persona for this user (read from the `is_active` column,
     * the single source of truth shared with the PersonaManager)
     */
    public function activePersona(): ?Persona
    {
        $persona = $this->personas()->where('is_active', true)->first();

        // A time-limited persona that reached its expiration is released
        // and the original persona is returned instead
        if ($persona instanceof Persona && $persona->hasExpired()) {
            return app('multipersona')->enforceExpiration($persona);
        }

        return $persona;
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

        // Persists the activation (deactivating the other personas of this
        // user) and points the session at the selected persona
        app('multipersona')->setActive($persona);

        return true;
    }

    /**
     * Switch to a specific persona for a limited time.
     *
     * `$ttl` is a number of seconds, a DateInterval or the date of the
     * expiration; when null, `multipersona.temporary.ttl` is used. Once the
     * expiration is reached, the persona that was active before the switch
     * is activated again.
     */
    public function switchToPersonaFor(Persona|int|string $persona, DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        if (is_scalar($persona)) {
            $persona = $this->personas()->find($persona);
        }

        if (! $persona instanceof Persona) {
            return false;
        }

        app('multipersona')->setActiveFor($persona, $ttl);

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
