<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Models;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use WeakMap;

/**
 * @property int $id
 * @property string $name
 * @property array $context
 * @property bool $is_active
 * @property \Carbon\Carbon|null $active_until
 * @property int|null $revert_to_persona_id
 * @property int $user_id
 * @property string $user_type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Model $user
 */
class Persona extends Model implements PersonaInterface
{
    use HasFactory;

    protected $fillable = [
        'name',
        'context',
        'is_active',
        'user_id',
        'user_type',
    ];

    protected $casts = [
        'context' => 'array',
        'is_active' => 'boolean',
        'active_until' => 'datetime',
    ];

    /**
     * Connections on which the expiration columns were found in the
     * personas table (remembered once they exist).
     *
     * @var WeakMap<\Illuminate\Database\Connection, true>|null
     */
    private static ?WeakMap $expirationSupport = null;

    /**
     * Get the user that owns the persona
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('multipersona.user_model', 'App\Models\User'));
    }

    /**
     * Get the persona ID
     */
    public function getId(): int|string
    {
        return $this->getKey();
    }

    /**
     * Get the persona name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the persona context data
     */
    public function getContext(): array
    {
        return $this->context ?? [];
    }

    /**
     * Check if this persona can access a given resource
     */
    public function canAccess(string $resource, array $context = []): bool
    {
        // Basic implementation - can be extended with more complex logic
        $personaContext = $this->getContext();

        if (isset($personaContext['permissions'])) {
            return in_array($resource, $personaContext['permissions'], true);
        }

        return true; // Default to allow access
    }

    /**
     * Get the user associated with this persona
     */
    public function getUser(): ?Model
    {
        return $this->user;
    }

    /**
     * Check if this persona is currently active in the session
     */
    public function isActive(): bool
    {
        $currentPersona = app('multipersona')->current();

        return $currentPersona !== null && $currentPersona->getId() === $this->getId();
    }

    /**
     * Set this persona as active.
     *
     * A user has at most one active persona: every other persona of the
     * same user is deactivated in the same transaction. The activation has
     * no time limit: an expiration set by a previous temporary activation
     * is removed.
     */
    public function activate(): self
    {
        return $this->persistActivation(null, null);
    }

    /**
     * Set this persona as active until the given date.
     *
     * Once the date is reached, the persona is deactivated and the persona
     * identified by `$revertToPersonaId` (if any) is activated again. The
     * expiration is enforced by the PersonaManager.
     */
    public function activateUntil(DateTimeInterface $expiresAt, int|string|null $revertToPersonaId = null): self
    {
        return $this->persistActivation($expiresAt, $revertToPersonaId);
    }

    /**
     * Deactivate this persona
     */
    public function deactivate(): self
    {
        $this->update(['is_active' => false]);

        if ($this->supportsExpiration()) {
            $this->writeExpiration(null, null);
        }

        return $this;
    }

    /**
     * Date at which this persona stops being active, or null when its
     * activation has no time limit.
     */
    public function expiresAt(): ?CarbonInterface
    {
        if (! array_key_exists('active_until', $this->attributes)) {
            return null;
        }

        $expiresAt = $this->getAttribute('active_until');

        return $expiresAt instanceof CarbonInterface ? $expiresAt : null;
    }

    /**
     * Check if this persona is active for a limited time only
     */
    public function isTemporary(): bool
    {
        return $this->getAttribute('is_active') === true && $this->expiresAt() instanceof CarbonInterface;
    }

    /**
     * Check if the time limit of this persona has been reached
     */
    public function hasExpired(): bool
    {
        $expiresAt = $this->expiresAt();

        return $expiresAt instanceof CarbonInterface && ! $expiresAt->isFuture();
    }

    /**
     * ID of the persona activated again when this temporary persona expires
     */
    public function getRevertPersonaId(): int|string|null
    {
        $id = $this->attributes['revert_to_persona_id'] ?? null;

        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * Check if the personas table has the columns of the time-limited
     * activation (added by the package migrations).
     */
    public function supportsExpiration(): bool
    {
        $connection = $this->getConnection();

        self::$expirationSupport ??= new WeakMap;

        if (isset(self::$expirationSupport[$connection])) {
            return true;
        }

        if (! $connection->getSchemaBuilder()->hasColumn($this->getTable(), 'active_until')) {
            return false;
        }

        self::$expirationSupport[$connection] = true;

        return true;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return PersonaFactory::new();
    }

    private function persistActivation(?DateTimeInterface $expiresAt, int|string|null $revertToPersonaId): self
    {
        $tracksExpiration = $this->supportsExpiration();

        if ($expiresAt instanceof DateTimeInterface && ! $tracksExpiration) {
            throw new LogicException(
                'The personas table has no "active_until" column: run the package migrations to use time-limited personas.'
            );
        }

        $this->getConnection()->transaction(function () use ($tracksExpiration, $expiresAt, $revertToPersonaId): void {
            $ownerId = $this->getAttribute('user_id');

            if ($ownerId !== null) {
                $deactivated = ['is_active' => false];

                if ($tracksExpiration) {
                    $deactivated['active_until'] = null;
                    $deactivated['revert_to_persona_id'] = null;
                }

                static::query()
                    ->where('user_id', $ownerId)
                    ->whereKeyNot($this->getKey())
                    ->where('is_active', true)
                    ->update($deactivated);
            }

            $this->update(['is_active' => true]);

            if ($tracksExpiration) {
                $this->writeExpiration($expiresAt, $revertToPersonaId);
            }
        });

        return $this;
    }

    /**
     * Write the expiration of this persona.
     *
     * The columns are written with a query, not from the dirty state of the
     * model: this instance may have been loaded before another instance of
     * the same persona was activated temporarily.
     */
    private function writeExpiration(?DateTimeInterface $expiresAt, int|string|null $revertToPersonaId): void
    {
        $query = static::query()->whereKey($this->getKey());

        if (! $expiresAt instanceof DateTimeInterface) {
            $revertToPersonaId = null;
            $query->whereNotNull('active_until');
        }

        $query->update([
            'active_until' => $expiresAt,
            'revert_to_persona_id' => $revertToPersonaId,
        ]);

        $this->forceFill([
            'active_until' => $expiresAt,
            'revert_to_persona_id' => $revertToPersonaId,
        ])->syncOriginalAttributes(['active_until', 'revert_to_persona_id']);
    }
}
