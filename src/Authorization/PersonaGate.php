<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Authorization;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Evaluates Gate abilities in the context of the active persona.
 *
 * Registered as a `Gate::before` callback when `multipersona.gate.enabled`
 * is true. For a user whose active persona defines a `permissions` list:
 *
 * - an ability granted by the persona (`canAccess()`) is allowed;
 * - any other ability is denied when `multipersona.gate.deny_unlisted` is
 *   true, otherwise the decision is left to the regular gates and policies.
 *
 * Users without an active persona, and personas without a `permissions`
 * list, never change the Gate decision.
 */
class PersonaGate
{
    public function __construct(private readonly PersonaManager $manager) {}

    /**
     * Register the resolver on the given Gate.
     */
    public static function register(Gate $gate): void
    {
        $gate->before(
            /** @param array<int|string, mixed> $arguments */
            static fn (mixed $user, string $ability, array $arguments = []): ?bool => app(self::class)->before($user, $ability, $arguments)
        );
    }

    /**
     * Gate::before callback: true grants, false denies, null defers.
     *
     * @param  array<int|string, mixed>  $arguments
     */
    public function before(mixed $user, string $ability, array $arguments = []): ?bool
    {
        if (! $user instanceof Authenticatable) {
            return null;
        }

        $persona = $this->personaFor($user);

        if (! $persona instanceof PersonaInterface) {
            return null;
        }

        $context = $persona->getContext();

        if (! isset($context['permissions']) || ! is_array($context['permissions'])) {
            return null;
        }

        if ($persona->canAccess($ability, $arguments)) {
            return true;
        }

        return config('multipersona.gate.deny_unlisted', false) ? false : null;
    }

    /**
     * Resolve the active persona of the given user.
     *
     * The persona held by the PersonaManager is used when it belongs to the
     * user; otherwise (for instance with `Gate::forUser()`) the active
     * persona of that user is read from the database.
     */
    public function personaFor(Authenticatable $user): ?PersonaInterface
    {
        $userId = $user->getAuthIdentifier();

        if (! is_int($userId) && ! is_string($userId)) {
            return null;
        }

        $current = $this->manager->current();

        if ($current instanceof Persona) {
            $ownerId = $current->getAttribute('user_id');

            if ((is_int($ownerId) || is_string($ownerId)) && (string) $ownerId === (string) $userId) {
                return $current;
            }
        }

        /** @var Persona|null $persona */
        $persona = Persona::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        return $persona;
    }
}
