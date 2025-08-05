<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class CachePersonaPermissions implements ShouldQueue
{
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

        // Cache for 1 hour
        Cache::put($cacheKey, [
            'persona_id' => $persona->getId(),
            'persona_name' => $persona->getName(),
            'context' => $context,
            'permissions' => $context['permissions'] ?? [],
            'role' => $context['role'] ?? null,
            'cached_at' => now(),
        ], 3600);
    }

    /**
     * Generate cache key for user-persona combination
     */
    private function getCacheKey(mixed $user, mixed $persona): string
    {
        $userId = method_exists($user, 'getKey') ? $user->getKey() : ($user->id ?? 'unknown');

        return "multipersona:permissions:{$userId}:{$persona->getId()}";
    }
}
