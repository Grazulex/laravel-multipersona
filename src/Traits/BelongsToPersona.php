<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Traits;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Scopes\PersonaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Filters a model by the active persona.
 *
 * Adds the `PersonaScope` global scope, so every query on the model only
 * returns the rows of the active persona, and fills the persona column of
 * new records. The column defaults to `multipersona.scope.column`
 * (`persona_id`); override `getPersonaColumn()` to change it for one model.
 *
 * @mixin Model
 */
trait BelongsToPersona
{
    /**
     * Register the global scope and fill the persona column on creation.
     */
    public static function bootBelongsToPersona(): void
    {
        static::addGlobalScope(new PersonaScope);

        static::creating(static function (Model $model): void {
            $column = PersonaScope::column($model);

            if ($model->getAttribute($column) !== null) {
                return;
            }

            $persona = PersonaScope::activePersona();

            if (! $persona instanceof PersonaInterface) {
                return;
            }

            $value = PersonaScope::valueFor($persona, PersonaScope::contextKey($model));

            if ($value !== null) {
                $model->setAttribute($column, $value);
            }
        });
    }

    /**
     * Column of this model holding the persona reference.
     */
    public function getPersonaColumn(): string
    {
        $column = config('multipersona.scope.column');

        return is_string($column) && $column !== '' ? $column : PersonaScope::DEFAULT_COLUMN;
    }

    /**
     * Key of the persona context the column is compared with, or null when
     * the column holds the persona ID.
     */
    public function getPersonaContextKey(): ?string
    {
        $key = config('multipersona.scope.context_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Persona column prefixed with the table of this model.
     */
    public function getQualifiedPersonaColumn(): string
    {
        return $this->qualifyColumn($this->getPersonaColumn());
    }

    /**
     * Persona owning this record (relevant when the column holds the
     * persona ID, i.e. when no context key is configured).
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, $this->getPersonaColumn());
    }

    /**
     * Query the model without the persona filter.
     */
    public function scopeWithoutPersonaScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(PersonaScope::class);
    }

    /**
     * Query the rows of the given persona instead of the active one.
     *
     * A persona instance is resolved like the active persona (ID or context
     * value); a scalar is compared with the column as is.
     */
    public function scopeForPersona(Builder $query, PersonaInterface|int|string $persona): Builder
    {
        $query->withoutGlobalScope(PersonaScope::class);

        $value = $persona instanceof PersonaInterface
            ? PersonaScope::valueFor($persona, $this->getPersonaContextKey())
            : $persona;

        if ($value === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->getQualifiedPersonaColumn(), $value);
    }
}
