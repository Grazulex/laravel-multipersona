<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Services;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;

class PersonaManager
{
    private ?PersonaInterface $currentPersona = null;

    /**
     * Get the current active persona
     */
    public function current(): ?PersonaInterface
    {
        if (! $this->currentPersona instanceof PersonaInterface) {
            $personaId = Session::get('active_persona_id');
            if ($personaId) {
                /** @var Persona|null $persona */
                $persona = Persona::query()->find($personaId);
                $this->currentPersona = $persona;
            }
        }

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

        // Set the new persona
        $this->currentPersona = $persona;
        Session::put('active_persona_id', $persona->getId());

        // Fire events
        PersonaActivated::dispatch($persona, $user, ['method' => 'setActive']);

        if ($previousPersona instanceof PersonaInterface) {
            PersonaSwitched::dispatch($persona, $previousPersona, $user, ['method' => 'setActive']);
        }

        return $this;
    }

    /**
     * Clear the active persona
     */
    public function clear(): self
    {
        $previousPersona = $this->currentPersona;
        $user = Auth::user();

        $this->currentPersona = null;
        Session::forget('active_persona_id');

        // Fire deactivation event if there was an active persona
        if ($previousPersona instanceof PersonaInterface) {
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
}
