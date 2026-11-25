<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This is the User model that will be used to retrieve your users. Set
    | this to any model or any fully-qualified class name of your User model.
    |
    */
    'user_model' => 'App\Models\User',

    /*
    |--------------------------------------------------------------------------
    | Personas Table
    |--------------------------------------------------------------------------
    |
    | This is the name of the table that will be created to store personas.
    | You can change this if you prefer a different table name.
    |
    */
    'table_name' => 'personas',

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | This is the session key that will be used to store the active persona ID.
    |
    */
    'session_key' => 'active_persona_id',

    /*
    |--------------------------------------------------------------------------
    | Default Permissions
    |--------------------------------------------------------------------------
    |
    | These are the default permissions that will be granted to all personas
    | if no specific permissions are defined in the persona context.
    |
    */
    'default_permissions' => [
        'read',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-activate First Persona
    |--------------------------------------------------------------------------
    |
    | When set to true, the first persona for a user will be automatically
    | activated when the user logs in (if no persona is currently active).
    |
    */
    'auto_activate_first' => true,

    /*
    |--------------------------------------------------------------------------
    | Middleware Aliases
    |--------------------------------------------------------------------------
    |
    | These are the middleware aliases that will be registered automatically.
    | You can disable this by setting to false and register them manually.
    |
    */
    'register_middleware' => true,

    'middleware_aliases' => [
        'persona.ensure' => Grazulex\LaravelMultiPersona\Middleware\EnsureActivePersona::class,
        'persona.set' => Grazulex\LaravelMultiPersona\Middleware\SetPersonaFromRequest::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bundled Listeners
    |--------------------------------------------------------------------------
    |
    | When set to true, the bundled listeners are registered automatically:
    | CachePersonaPermissions (PersonaActivated), LogPersonaSwitch
    | (PersonaSwitched) and ForgetPersonaPermissions (PersonaSwitched and
    | PersonaDeactivated). The permissions cache of a persona is also
    | forgotten when the persona is updated or deleted. Set this to false
    | if you register these listeners yourself.
    |
    */
    'register_listeners' => true,

    /*
    |--------------------------------------------------------------------------
    | Gate Integration
    |--------------------------------------------------------------------------
    |
    | When enabled, abilities checked through the Gate ($user->can(),
    | Gate::allows(), @can, authorize()...) are evaluated in the context of
    | the user's active persona. An ability listed in the `permissions` of
    | the active persona is granted. With `deny_unlisted` set to true, any
    | other ability is denied while that persona is active; otherwise the
    | decision is left to your regular gates and policies. Users without an
    | active persona, and personas without a `permissions` list, are not
    | affected.
    |
    */
    'gate' => [
        'enabled' => false,
        'deny_unlisted' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Persona Data Scope
    |--------------------------------------------------------------------------
    |
    | Settings of the BelongsToPersona trait, which filters the queries of a
    | model by the active persona. `column` is the column of your models that
    | references the persona. By default it holds the persona ID; set
    | `context_key` (for instance 'company_id') to compare it with that value
    | of the persona context instead. When no persona is active, queries are
    | not filtered unless `strict` is true, in which case they return no
    | row. A model can override the column and the context key with
    | getPersonaColumn() and getPersonaContextKey().
    |
    */
    'scope' => [
        'column' => 'persona_id',
        'context_key' => null,
        'strict' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Persona Switch Audit
    |--------------------------------------------------------------------------
    |
    | When `enabled` is true, the RecordPersonaSwitch listener writes every
    | PersonaSwitched event to the `table` audit table (user, date, source and
    | target persona) and dispatches PersonaSwitchLogged afterwards. This
    | setting does not depend on `register_listeners`.
    |
    | `log_channel` is the log channel used by the LogPersonaSwitch listener
    | (for instance a dedicated 'audit' channel defined in config/logging.php).
    | When null, the default log channel is used.
    |
    */
    'audit' => [
        'enabled' => false,
        'table' => 'persona_switch_logs',
        'log_channel' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Time-limited Personas
    |--------------------------------------------------------------------------
    |
    | `ttl` is the default lifetime, in seconds, of a persona activated with
    | PersonaManager::setActiveFor(), PersonaManager::switchToFor() or
    | HasPersonas::switchToPersonaFor() when no duration is given. Once it
    | has elapsed, the persona is deactivated and the persona that was
    | active before the switch is activated again. Personas activated with
    | setActive(), switchTo() or switchToPersona() never expire.
    |
    */
    'temporary' => [
        'ttl' => 900,
    ],
];
