<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Events;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a time-limited persona has reached its expiration and the
 * user has been returned to the original persona (if any).
 */
class PersonaExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PersonaInterface $persona,
        public readonly ?PersonaInterface $restoredPersona = null,
        public readonly mixed $user = null,
        public readonly array $context = []
    ) {}

    /**
     * Get the persona that expired
     */
    public function getPersona(): PersonaInterface
    {
        return $this->persona;
    }

    /**
     * Get the persona that was active before the temporary switch and has
     * been activated again (null when there was none, or it no longer exists)
     */
    public function getRestoredPersona(): ?PersonaInterface
    {
        return $this->restoredPersona;
    }

    /**
     * Get the owner of the expired persona: the authenticated user when the
     * persona is theirs, otherwise the user identifier
     */
    public function getUser(): mixed
    {
        return $this->user;
    }

    /**
     * Get additional context data
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
