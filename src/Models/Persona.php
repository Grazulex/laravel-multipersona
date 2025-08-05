<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Models;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property array $context
 * @property bool $is_active
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
    ];

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
     * Check if this persona is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Set this persona as active
     */
    public function activate(): self
    {
        $this->update(['is_active' => true]);

        return $this;
    }

    /**
     * Deactivate this persona
     */
    public function deactivate(): self
    {
        $this->update(['is_active' => false]);

        return $this;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return PersonaFactory::new();
    }
}
