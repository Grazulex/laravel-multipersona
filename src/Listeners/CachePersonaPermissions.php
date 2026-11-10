<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Listeners;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class CachePersonaPermissions implements ShouldQueue
{
    /**
     * Prefix of the cache keys written by this listener.
     */
    public const CACHE_PREFIX = 'multipersona:permissions';

    /**
     * Lifetime of a cached entry, in seconds.
     */
    public const TTL = 3600;

    /**
     * Build the cache key of a user-persona combination.
     *
     * The user can be a model (its key is used), an object exposing an `id`
     * property, or the user identifier itself.
     */
    public static function cacheKey(mixed $user, PersonaInterface|int|string $persona): string
    {
        $personaId = $persona instanceof PersonaInterface ? $persona->getId() : $persona;

        return self::CACHE_PREFIX.':'.self::userIdentifier($user).':'.$personaId;
    }

    /**
     * Read the cached permissions of a user-persona combination.
     *
     * @return array<string, mixed>|null
     */
    public static function cached(mixed $user, PersonaInterface|int|string $persona): ?array
    {
        $value = Cache::get(self::cacheKey($user, $persona));

        return is_array($value) ? $value : null;
    }

    /**
     * Remove the cached permissions of a user-persona combination.
     */
    public static function forget(mixed $user, PersonaInterface|int|string $persona): void
    {
        Cache::forget(self::cacheKey($user, $persona));
    }

    /**
     * Handle the event.
     */
    public function handle(PersonaActivated $event): void
    {
        $persona = $event->getPersona();
        $user = $event->getUser();

        if ($user === null) {
            return;
        }

        // Cache persona permissions for quick access
        $cacheKey = $this->getCacheKey($user, $persona);
        $context = $persona->getContext();

        Cache::put($cacheKey, [
            'persona_id' => $persona->getId(),
            'persona_name' => $persona->getName(),
            'context' => $context,
            'permissions' => $context['permissions'] ?? [],
            'role' => $context['role'] ?? null,
            'cached_at' => now(),
        ], self::TTL);
    }

    private static function userIdentifier(mixed $user): int|string
    {
        if (is_int($user) || is_string($user)) {
            return $user;
        }

        if (is_object($user)) {
            if (method_exists($user, 'getKey')) {
                $key = $user->getKey();

                return is_int($key) || is_string($key) ? $key : 'unknown';
            }

            $id = $user->id ?? null;

            return is_int($id) || is_string($id) ? $id : 'unknown';
        }

        return 'unknown';
    }

    /**
     * Generate cache key for user-persona combination
     */
    private function getCacheKey(mixed $user, PersonaInterface $persona): string
    {
        return self::cacheKey($user, $persona);
    }
}
