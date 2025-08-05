<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Events;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PersonaSwitched
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PersonaInterface $persona,
        public readonly ?PersonaInterface $previousPersona = null,
        public readonly mixed $user = null,
        public readonly array $context = []
    ) {}

    /**
     * Get the persona that was activated
     */
    public function getPersona(): PersonaInterface
    {
        return $this->persona;
    }

    /**
     * Get the previously active persona (if any)
     */
    public function getPreviousPersona(): ?PersonaInterface
    {
        return $this->previousPersona;
    }

    /**
     * Get the user who switched personas
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

    /**
     * Check if this was an initial activation (no previous persona)
     */
    public function isInitialActivation(): bool
    {
        return ! $this->previousPersona instanceof PersonaInterface;
    }

    /**
     * Get the persona switch summary
     */
    public function getSummary(): array
    {
        return [
            'new_persona' => [
                'id' => $this->persona->getId(),
                'name' => $this->persona->getName(),
                'context' => $this->persona->getContext(),
            ],
            'previous_persona' => $this->previousPersona instanceof PersonaInterface ? [
                'id' => $this->previousPersona->getId(),
                'name' => $this->previousPersona->getName(),
                'context' => $this->previousPersona->getContext(),
            ] : null,
            'user' => $this->user ? [
                'id' => method_exists($this->user, 'getKey') ? $this->user->getKey() : ($this->user->id ?? null),
                'type' => get_class($this->user),
            ] : null,
            'context' => $this->context,
            'timestamp' => now(),
            'is_initial_activation' => $this->isInitialActivation(),
        ];
    }
}
