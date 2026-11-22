<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Scopes;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts the queries of a model to the rows of the active persona.
 *
 * Added by the `BelongsToPersona` trait. The filtered column and the value
 * it is compared with are resolved per model (see `column()` and
 * `contextKey()`):
 *
 * - with an active persona, only the rows whose column matches the persona
 *   (its ID, or a value of its context) are returned; a persona that has no
 *   value for the configured context key matches no row;
 * - without an active persona, the query is not filtered, unless
 *   `multipersona.scope.strict` is true, in which case it returns no row.
 */
class PersonaScope implements Scope
{
    public const string DEFAULT_COLUMN = 'persona_id';

    /**
     * Column holding the persona reference for the given model.
     */
    public static function column(Model $model): string
    {
        $column = method_exists($model, 'getPersonaColumn')
            ? $model->getPersonaColumn()
            : config('multipersona.scope.column');

        return is_string($column) && $column !== '' ? $column : self::DEFAULT_COLUMN;
    }

    /**
     * Key of the persona context compared with the column, or null when the
     * column holds the persona ID.
     */
    public static function contextKey(Model $model): ?string
    {
        $key = method_exists($model, 'getPersonaContextKey')
            ? $model->getPersonaContextKey()
            : config('multipersona.scope.context_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Value the column must hold for a row to belong to the given persona.
     */
    public static function valueFor(PersonaInterface $persona, ?string $contextKey = null): int|string|null
    {
        if ($contextKey === null) {
            return $persona->getId();
        }

        $value = data_get($persona->getContext(), $contextKey);

        return is_int($value) || is_string($value) ? $value : null;
    }

    /**
     * The persona currently active, if any.
     */
    public static function activePersona(): ?PersonaInterface
    {
        return app(PersonaManager::class)->current();
    }

    /**
     * Whether queries must return no row when no persona is active.
     */
    public static function isStrict(): bool
    {
        return (bool) config('multipersona.scope.strict', false);
    }

    /**
     * Apply the scope to the given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $persona = self::activePersona();

        if (! $persona instanceof PersonaInterface) {
            if (self::isStrict()) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $value = self::valueFor($persona, self::contextKey($model));

        if ($value === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn(self::column($model)), $value);
    }
}
