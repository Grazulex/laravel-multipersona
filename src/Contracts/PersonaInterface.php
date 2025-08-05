<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PersonaInterface
{
    /**
     * Get the persona ID
     */
    public function getId(): int|string;

    /**
     * Get the persona name
     */
    public function getName(): string;

    /**
     * Get the persona context data
     */
    public function getContext(): array;

    /**
     * Check if this persona can access a given resource
     */
    public function canAccess(string $resource, array $context = []): bool;

    /**
     * Get the user associated with this persona
     */
    public function getUser(): ?Model;

    /**
     * Check if this persona is active
     */
    public function isActive(): bool;

    /**
     * Set this persona as active
     */
    public function activate(): self;

    /**
     * Deactivate this persona
     */
    public function deactivate(): self;
}
