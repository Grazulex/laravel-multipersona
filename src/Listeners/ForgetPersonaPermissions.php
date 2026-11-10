<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Listeners;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;

/**
 * Invalidates the permissions cached by CachePersonaPermissions when a
 * persona stops being the active one.
 *
 * This listener runs synchronously so that stale permissions are never
 * served once the persona has changed.
 */
class ForgetPersonaPermissions
{
    /**
     * Forget the cache of a deactivated persona.
     */
    public function handleDeactivated(PersonaDeactivated $event): void
    {
        $user = $event->getUser();

        if ($user === null) {
            return;
        }

        CachePersonaPermissions::forget($user, $event->getPersona());
    }

    /**
     * Forget the cache of the persona that was replaced by a switch.
     */
    public function handleSwitched(PersonaSwitched $event): void
    {
        $user = $event->getUser();
        $previous = $event->getPreviousPersona();

        if ($user === null || ! $previous instanceof PersonaInterface) {
            return;
        }

        // Re-activating the same persona must keep its fresh cache entry
        if ($previous->getId() === $event->getPersona()->getId()) {
            return;
        }

        CachePersonaPermissions::forget($user, $previous);
    }

    /**
     * Handle either event.
     */
    public function handle(PersonaDeactivated|PersonaSwitched $event): void
    {
        if ($event instanceof PersonaDeactivated) {
            $this->handleDeactivated($event);

            return;
        }

        $this->handleSwitched($event);
    }
}
