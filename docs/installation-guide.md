# Installation Guide - Laravel MultiPersona

## Prerequisites

- PHP 8.3+
- Laravel 10.x or 11.x
- Laravel-supported database (MySQL, PostgreSQL, SQLite, etc.)

## Installation

### 1. Install via Composer

```bash
composer require grazulex/laravel-multipersona
```

### 2. Publish configuration and migration files

```bash
# Publish configuration
php artisan vendor:publish --tag=multipersona-config

# Publish migrations
php artisan vendor:publish --tag=multipersona-migrations
```

### 3. Run migrations

```bash
php artisan migrate
```

### 4. Configure User Model

Add the `HasPersonas` trait to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Grazulex\LaravelMultiPersona\Traits\HasPersonas;

class User extends Authenticatable
{
    use HasPersonas;
    
    // ... rest of your model
}
```

## Configuration

### Configuration File

The `config/multipersona.php` file contains all configurable options:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    | Specify the user model to use with personas
    */
    'user_model' => env('MULTIPERSONA_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Personas Table
    |--------------------------------------------------------------------------
    | Name of the table to store personas
    */
    'table_name' => env('MULTIPERSONA_TABLE_NAME', 'personas'),

    /*
    |--------------------------------------------------------------------------
    | Default Permissions
    |--------------------------------------------------------------------------
    | Permissions granted by default to any new persona
    */
    'default_permissions' => [
        'read',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    */
    'register_middleware' => true,
    'middleware_aliases' => [
        'persona.required' => \Grazulex\LaravelMultiPersona\Middleware\EnsureActivePersona::class,
        'persona.from_request' => \Grazulex\LaravelMultiPersona\Middleware\SetPersonaFromRequest::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'permissions_ttl' => 3600, // 1 hour
        'prefix' => 'multipersona',
    ],
];
```

### Environment Variables

You can customize the configuration via the `.env` file:

```env
# Custom user model
MULTIPERSONA_USER_MODEL=App\\Models\\CustomUser

# Custom table
MULTIPERSONA_TABLE_NAME=user_personas
```

## Installation Verification

Create a simple test to verify everything works:

```php
// In a test route or controller
use App\Models\User;

$user = User::first();

// Create a persona
$persona = $user->createPersona([
    'name' => 'Administrator',
    'context' => [
        'role' => 'admin',
        'permissions' => ['read', 'write', 'delete']
    ]
]);

// Activate the persona
$user->switchToPersona($persona);

// Verify
dd([
    'active_persona' => persona(),
    'all_personas' => personas($user),
]);
```

## Troubleshooting

### Common Issues

1. **"Table doesn't exist" Error**
   ```bash
   php artisan migrate:status
   php artisan migrate
   ```

2. **HasPersonas trait not found**
   ```bash
   composer dump-autoload
   ```

3. **Configuration not published**
   ```bash
   php artisan vendor:publish --tag=multipersona-config --force
   ```

4. **Permissions not configured**
   Verify that your User model is using the `HasPersonas` trait.

## Next Steps

Once installation is complete, check out:

- [Basic Usage Guide](usage-guide.md)
- [Practical Examples](examples.md)
- [Events Guide](events-guide.md)
- [API Reference](api-reference.md)
