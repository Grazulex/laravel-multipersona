<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Listeners;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitchLogged;
use Grazulex\LaravelMultiPersona\Models\PersonaSwitchLog;

/**
 * Writes every persona switch to the audit table (user, date, source and
 * target persona), then dispatches PersonaSwitchLogged.
 *
 * This listener runs synchronously so that the recorded date is the date of
 * the switch.
 */
class RecordPersonaSwitch
{
    /**
     * Handle the event.
     */
    public function handle(PersonaSwitched $event): void
    {
        $persona = $event->getPersona();
        $previous = $event->getPreviousPersona();
        $user = $event->getUser();

        $log = PersonaSwitchLog::query()->create([
            'user_id' => $this->numericKey($this->userKey($user)),
            'user_type' => is_object($user) ? $user::class : null,
            'from_persona_id' => $previous instanceof PersonaInterface ? $this->numericKey($previous->getId()) : null,
            'from_persona_name' => $previous?->getName(),
            'to_persona_id' => $this->numericKey($persona->getId()),
            'to_persona_name' => $persona->getName(),
            'context' => $event->getContext() === [] ? null : $event->getContext(),
            'switched_at' => now(),
        ]);

        PersonaSwitchLogged::dispatch($log, $user);
    }

    private function userKey(mixed $user): mixed
    {
        if (is_object($user)) {
            return method_exists($user, 'getKey') ? $user->getKey() : ($user->id ?? null);
        }

        return $user;
    }

    private function numericKey(mixed $key): ?int
    {
        if (is_int($key)) {
            return $key;
        }

        if (is_string($key) && ctype_digit($key)) {
            return (int) $key;
        }

        return null;
    }
}
