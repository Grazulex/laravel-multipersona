<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Audit entry of a persona switch.
 *
 * The persona names are stored alongside their IDs so that an entry stays
 * readable after the persona has been renamed or deleted.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_type
 * @property int|null $from_persona_id
 * @property string|null $from_persona_name
 * @property int|null $to_persona_id
 * @property string $to_persona_name
 * @property array|null $context
 * @property \Carbon\Carbon $switched_at
 * @property-read Persona|null $fromPersona
 * @property-read Persona|null $toPersona
 */
class PersonaSwitchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_type',
        'from_persona_id',
        'from_persona_name',
        'to_persona_id',
        'to_persona_name',
        'context',
        'switched_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'from_persona_id' => 'integer',
        'to_persona_id' => 'integer',
        'context' => 'array',
        'switched_at' => 'datetime',
    ];

    /**
     * The table is configurable with `multipersona.audit.table`.
     */
    #[Override]
    public function getTable(): string
    {
        $table = config('multipersona.audit.table', 'persona_switch_logs');

        return is_string($table) && $table !== '' ? $table : 'persona_switch_logs';
    }

    /**
     * The persona that was active before the switch.
     */
    public function fromPersona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'from_persona_id');
    }

    /**
     * The persona that became active.
     */
    public function toPersona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'to_persona_id');
    }

    /**
     * The user who switched persona.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('multipersona.user_model', 'App\Models\User'), 'user_id');
    }

    /**
     * Limit the query to the switches of a user.
     */
    public function scopeForUser(Builder $query, Model|int|string $user): Builder
    {
        return $query->where('user_id', $user instanceof Model ? $user->getKey() : $user);
    }

    /**
     * Limit the query to the switches from or to a persona.
     */
    public function scopeForPersona(Builder $query, Persona|int|string $persona): Builder
    {
        $id = $persona instanceof Persona ? $persona->getKey() : $persona;

        return $query->where(function (Builder $query) use ($id): void {
            $query->where('from_persona_id', $id)->orWhere('to_persona_id', $id);
        });
    }
}
