<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Services;

use Carbon\CarbonInterface;
use DateInterval;
use DateTimeInterface;
use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaExpired;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Listeners\CachePersonaPermissions;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;

class PersonaManager
{
    /**
     * Lifetime of a temporary persona, in seconds, when none is given and
     * `multipersona.temporary.ttl` is not set.
     */
    public const DEFAULT_TTL = 900;

    private const SESSION_KEY = 'active_persona_id';

    private ?PersonaInterface $currentPersona = null;

    /**
     * Get the current active persona.
     *
     * The `is_active` column is the source of truth. The session only keeps
     * a pointer to the active persona: if that pointer no longer matches the
     * database (for instance because another persona was activated
     * elsewhere), it is resynchronised with the persona that is active for
     * the same user.
     */
    public function current(): ?PersonaInterface
    {
        // A time-limited persona that reached its expiration is released
        // here, even when it was already resolved (long-running processes)
        if ($this->currentPersona instanceof Persona && $this->currentPersona->hasExpired()) {
            $this->currentPersona = $this->enforceExpiration($this->currentPersona);

            return $this->currentPersona;
        }

        if ($this->currentPersona instanceof PersonaInterface) {
            return $this->currentPersona;
        }

        $personaId = Session::get(self::SESSION_KEY);
        $ownerId = Auth::id();
        $persona = null;

        if ($personaId) {
            /** @var Persona|null $sessionPersona */
            $sessionPersona = Persona::query()->find($personaId);

            if ($sessionPersona instanceof Persona && $sessionPersona->is_active) {
                $persona = $sessionPersona;
            } elseif ($sessionPersona instanceof Persona) {
                $ownerId = $sessionPersona->getAttribute('user_id');
            }
        }

        if (! $persona instanceof Persona && $ownerId !== null) {
            /** @var Persona|null $persona */
            $persona = Persona::query()
                ->where('user_id', $ownerId)
                ->where('is_active', true)
                ->first();
        }

        if ($persona instanceof Persona && $persona->hasExpired()) {
            $persona = $this->enforceExpiration($persona);
        }

        if ($persona instanceof Persona) {
            Session::put(self::SESSION_KEY, $persona->getId());
        } elseif ($personaId) {
            Session::forget(self::SESSION_KEY);
        }

        $this->currentPersona = $persona;

        return $this->currentPersona;
    }

    /**
     * Set the active persona
     */
    public function setActive(PersonaInterface|int|string $persona): self
    {
        if (is_scalar($persona)) {
            /** @var Persona|null $persona */
            $persona = Persona::query()->find($persona);
        }

        if ($persona === null) {
            throw new InvalidArgumentException('Persona not found');
        }

        // Get the previous persona for event
        $previousPersona = $this->currentPersona;
        $user = Auth::user();

        // Persist the activation (deactivates the user's other personas),
        // then point the session at it
        $persona->activate();
        $this->currentPersona = $persona;
        Session::put(self::SESSION_KEY, $persona->getId());

        // Fire events
        PersonaActivated::dispatch($persona, $user, ['method' => 'setActive']);

        if ($previousPersona instanceof PersonaInterface) {
            PersonaSwitched::dispatch($persona, $previousPersona, $user, ['method' => 'setActive']);
        }

        return $this;
    }

    /**
     * Set the active persona for a limited time.
     *
     * `$ttl` is a number of seconds, a DateInterval or the date of the
     * expiration; when null, `multipersona.temporary.ttl` is used. Once the
     * expiration is reached, the persona is deactivated and the persona that
     * was active before the switch is activated again (see
     * `enforceExpiration()`).
     *
     * A temporary switch made while another temporary persona is active
     * returns to the persona that was active before the first one. Calling
     * this method again for the persona that is already temporarily active
     * only moves its expiration.
     */
    public function setActiveFor(PersonaInterface|int|string $persona, DateTimeInterface|DateInterval|int|null $ttl = null): self
    {
        if (is_scalar($persona)) {
            /** @var Persona|null $persona */
            $persona = Persona::query()->find($persona);
        }

        if ($persona === null) {
            throw new InvalidArgumentException('Persona not found');
        }

        if (! $persona instanceof Persona) {
            throw new InvalidArgumentException('Only Persona models can be activated for a limited time');
        }

        $expiresAt = $this->expirationFrom($ttl);
        $originalPersonaId = $this->originalPersonaIdFor($persona);

        $previousPersona = $this->currentPersona;
        $user = Auth::user();
        $context = ['method' => 'setActiveFor', 'expires_at' => $expiresAt->toIso8601String()];

        $persona->activateUntil($expiresAt, $originalPersonaId);
        $this->currentPersona = $persona;
        Session::put(self::SESSION_KEY, $persona->getId());

        PersonaActivated::dispatch($persona, $user, $context);

        if ($previousPersona instanceof PersonaInterface) {
            PersonaSwitched::dispatch($persona, $previousPersona, $user, $context);
        }

        return $this;
    }

    /**
     * Switch to a persona for a limited time if allowed
     */
    public function switchToFor(PersonaInterface|int|string $persona, DateTimeInterface|DateInterval|int|null $ttl = null, ?Model $user = null): bool
    {
        if (! $this->canActivate($persona, $user)) {
            return false;
        }

        $this->setActiveFor($persona, $ttl);

        return true;
    }

    /**
     * Check if the current persona is active for a limited time only
     */
    public function isTemporary(): bool
    {
        $persona = $this->current();

        return $persona instanceof Persona && $persona->isTemporary();
    }

    /**
     * Get the date at which the current persona expires, or null when it has
     * no time limit
     */
    public function expiresAt(): ?CarbonInterface
    {
        $persona = $this->current();

        return $persona instanceof Persona ? $persona->expiresAt() : null;
    }

    /**
     * Apply the time limit of the given persona.
     *
     * A persona that has not expired is returned as is. Otherwise it is
     * deactivated, the persona that was active before the temporary switch
     * is activated again (when it still exists and belongs to the same
     * user), and the events are dispatched: PersonaActivated and
     * PersonaSwitched for the restored persona, or PersonaDeactivated when
     * there is none, then PersonaExpired. Returns the persona that is active
     * for the user afterwards.
     */
    public function enforceExpiration(Persona $persona): ?Persona
    {
        if (! $persona->hasExpired()) {
            return $persona;
        }

        $this->release($persona);

        $ownerId = $persona->getAttribute('user_id');

        if ($ownerId === null) {
            return null;
        }

        /** @var Persona|null $active */
        $active = Persona::query()
            ->where('user_id', $ownerId)
            ->where('is_active', true)
            ->first();

        return $active;
    }

    /**
     * Release every time-limited persona that has reached its expiration,
     * for all users, and return the number of personas released.
     *
     * Expirations are applied when the active persona is read; schedule this
     * method to also release the personas of users who make no request.
     */
    public function releaseExpired(): int
    {
        if (! (new Persona)->supportsExpiration()) {
            return 0;
        }

        $released = 0;

        /** @var Collection<int, Persona> $expired */
        $expired = Persona::query()
            ->where('is_active', true)
            ->whereNotNull('active_until')
            ->where('active_until', '<=', Carbon::now())
            ->get();

        foreach ($expired as $persona) {
            if ($this->release($persona)) {
                $released++;
            }
        }

        return $released;
    }

    /**
     * Clear the active persona
     */
    public function clear(): self
    {
        $previousPersona = $this->current();
        $user = Auth::user();

        $this->currentPersona = null;
        Session::forget(self::SESSION_KEY);

        // Persist the deactivation and fire the event if there was an active persona
        if ($previousPersona instanceof PersonaInterface) {
            $previousPersona->deactivate();
            PersonaDeactivated::dispatch($previousPersona, $user, ['method' => 'clear']);
        }

        return $this;
    }

    /**
     * Get all personas for a user
     */
    public function forUser(Model $user): Collection
    {
        // Use direct database query instead of model relationship
        // This allows us to work with any user object that has an id
        /** @var Collection $personas */
        $personas = Persona::query()->where('user_id', $user->getKey())->get();

        return $personas;
    }

    /**
     * Check if a persona can be activated by the current user
     */
    public function canActivate(PersonaInterface|int|string $persona, ?Model $user = null): bool
    {
        if (is_scalar($persona)) {
            /** @var Persona|null $persona */
            $persona = Persona::query()->find($persona);
        }

        if ($persona === null) {
            return false;
        }

        $user = $user instanceof Model ? $user : Auth::user();
        if ($user === null) {
            return false;
        }

        // Check if user has the HasPersonas trait and personas relationship
        if (method_exists($user, 'personas')) {
            $personas = $user->personas();
            if ($personas instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                return $personas->where('id', $persona->getId())->exists();
            }
        }

        return false;
    }

    /**
     * Switch to a persona if allowed
     */
    public function switchTo(PersonaInterface|int|string $persona, ?Model $user = null): bool
    {
        if (! $this->canActivate($persona, $user)) {
            return false;
        }

        $this->setActive($persona);

        return true;
    }

    /**
     * Get the persona ID for the current active persona
     */
    public function id(): int|string|null
    {
        return $this->current()?->getId();
    }

    /**
     * Get the persona name for the current active persona
     */
    public function name(): ?string
    {
        return $this->current()?->getName();
    }

    /**
     * Get the context for the current active persona
     */
    public function context(): array
    {
        return $this->current()?->getContext() ?? [];
    }

    /**
     * Check if there's an active persona
     */
    public function hasActive(): bool
    {
        return $this->current() instanceof PersonaInterface;
    }

    /**
     * Deactivate an expired persona and return its owner to the original
     * persona. Returns false when the persona was not released (it is no
     * longer active, or its expiration was moved or removed meanwhile).
     */
    private function release(Persona $persona): bool
    {
        // Only one process releases a given expiration: the update is
        // conditioned on the state read from the database
        $claimed = Persona::query()
            ->whereKey($persona->getKey())
            ->where('is_active', true)
            ->whereNotNull('active_until')
            ->where('active_until', '<=', Carbon::now())
            ->update([
                'is_active' => false,
                'active_until' => null,
                'revert_to_persona_id' => null,
            ]);

        if ($claimed === 0) {
            return false;
        }

        $personaId = $persona->getId();
        $ownerId = $persona->getAttribute('user_id');
        $originalPersonaId = $persona->getRevertPersonaId();

        $persona->forceFill([
            'is_active' => false,
            'active_until' => null,
            'revert_to_persona_id' => null,
        ])->syncOriginalAttributes(['is_active', 'active_until', 'revert_to_persona_id']);

        $restored = null;

        if ($originalPersonaId !== null && $ownerId !== null) {
            /** @var Persona|null $restored */
            $restored = Persona::query()
                ->whereKey($originalPersonaId)
                ->where('user_id', $ownerId)
                ->first();
        }

        if ($restored instanceof Persona) {
            $restored->activate();
        }

        // Keep this manager and the session in sync when they pointed at
        // the expired persona
        if ($this->currentPersona instanceof PersonaInterface
            && (string) $this->currentPersona->getId() === (string) $personaId) {
            $this->currentPersona = $restored;
        }

        $sessionPersonaId = Session::get(self::SESSION_KEY);

        if (is_scalar($sessionPersonaId) && (string) $sessionPersonaId === (string) $personaId) {
            if ($restored instanceof Persona) {
                Session::put(self::SESSION_KEY, $restored->getId());
            } else {
                Session::forget(self::SESSION_KEY);
            }
        }

        // The permissions cached for the expired persona must not outlive
        // it, whoever triggers the release
        if (is_int($ownerId) || is_string($ownerId)) {
            CachePersonaPermissions::forget($ownerId, $persona);
        }

        $user = $this->ownerOf($persona);
        $context = ['method' => 'expire'];

        if ($restored instanceof Persona) {
            PersonaActivated::dispatch($restored, $user, $context);
            PersonaSwitched::dispatch($restored, $persona, $user, $context);
        } else {
            PersonaDeactivated::dispatch($persona, $user, $context);
        }

        PersonaExpired::dispatch($persona, $restored, $user, $context);

        return true;
    }

    /**
     * Owner of a persona, for the events: the authenticated user when the
     * persona is theirs, otherwise the owner loaded with the configured
     * user model (null when it cannot be loaded).
     */
    private function ownerOf(Persona $persona): mixed
    {
        $ownerId = $persona->getAttribute('user_id');
        $authId = Auth::id();

        if ($authId !== null && is_scalar($ownerId) && (string) $authId === (string) $ownerId) {
            return Auth::user();
        }

        $userModel = config('multipersona.user_model');

        if (is_string($userModel) && class_exists($userModel) && is_subclass_of($userModel, Model::class)) {
            return $persona->getUser();
        }

        return null;
    }

    /**
     * ID of the persona to return to once a temporary activation of the
     * given persona expires.
     */
    private function originalPersonaIdFor(Persona $persona): int|string|null
    {
        $ownerId = $persona->getAttribute('user_id');

        if ($ownerId === null) {
            return null;
        }

        /** @var Persona|null $active */
        $active = Persona::query()
            ->where('user_id', $ownerId)
            ->where('is_active', true)
            ->first();

        if ($active instanceof Persona && $active->hasExpired()) {
            $active = $this->enforceExpiration($active);
        }

        if (! $active instanceof Persona) {
            return null;
        }

        // Already in a temporary switch (of this persona or another one):
        // keep returning to the persona that was active before it
        $originalPersonaId = $active->isTemporary() ? $active->getRevertPersonaId() : $active->getKey();

        if (! is_int($originalPersonaId) && ! is_string($originalPersonaId)) {
            return null;
        }

        return (string) $originalPersonaId === (string) $persona->getId() ? null : $originalPersonaId;
    }

    /**
     * Resolve the expiration date of a temporary activation.
     */
    private function expirationFrom(DateTimeInterface|DateInterval|int|null $ttl): CarbonInterface
    {
        $ttl ??= config('multipersona.temporary.ttl', self::DEFAULT_TTL);

        $now = Carbon::now();

        if ($ttl instanceof DateTimeInterface) {
            $expiresAt = Carbon::instance($ttl)->setTimezone($now->getTimezone());
        } elseif ($ttl instanceof DateInterval) {
            $expiresAt = $now->copy()->add($ttl);
        } elseif (is_numeric($ttl)) {
            $expiresAt = $now->copy()->addSeconds((int) $ttl);
        } else {
            throw new InvalidArgumentException('The duration of a temporary persona must be a number of seconds, a DateInterval or a date');
        }

        if (! $expiresAt->isFuture()) {
            throw new InvalidArgumentException('The expiration of a temporary persona must be in the future');
        }

        return $expiresAt;
    }
}
