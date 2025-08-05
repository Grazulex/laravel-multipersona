<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Events;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PersonaDeactivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PersonaInterface $persona,
        public readonly mixed $user = null,
        public readonly array $context = []
    ) {}

    /**
     * Get the deactivated persona
     */
    public function getPersona(): PersonaInterface
    {
        return $this->persona;
    }

    /**
     * Get the user who deactivated the persona
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
     * Get the deactivation summary
     */
    public function getSummary(): array
    {
        return [
            'persona' => [
                'id' => $this->persona->getId(),
                'name' => $this->persona->getName(),
                'context' => $this->persona->getContext(),
            ],
            'user' => $this->user ? [
                'id' => method_exists($this->user, 'getKey') ? $this->user->getKey() : ($this->user->id ?? null),
                'type' => get_class($this->user),
            ] : null,
            'context' => $this->context,
            'timestamp' => now(),
        ];
    }
}
