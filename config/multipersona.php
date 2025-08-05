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
];
