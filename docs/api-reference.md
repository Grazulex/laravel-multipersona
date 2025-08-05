# API Reference - Laravel MultiPersona

## Introduction

This document provides a complete reference for the Laravel MultiPersona package, including all classes, methods, interfaces, and configuration options.

## Core Interfaces

### PersonaInterface

The main contract that defines persona behavior.

```php
namespace Grazulex\LaravelMultiPersona\Contracts;

interface PersonaInterface
{
    /**
     * Get the unique identifier for the persona
     */
    public function getId(): int|string;
    
    /**
     * Get the display name of the persona
     */
    public function getName(): string;
    
    /**
     * Get the context data for the persona
     */
    public function getContext(): array;
    
    /**
     * Check if the persona can access a resource
     */
    public function canAccess(string $resource, array $context = []): bool;
    
    /**
     * Get the user associated with this persona
     */
    public function getUser(): ?Model;
    
    /**
     * Check if the persona is currently active
     */
    public function isActive(): bool;
    
    /**
     * Activate this persona
     */
    public function activate(): self;
    
    /**
     * Deactivate this persona
     */
    public function deactivate(): self;
}
```

## Core Classes

### Persona Model

```php
namespace Grazulex\LaravelMultiPersona\Models;

class Persona extends Model implements PersonaInterface
{
    protected $fillable = [
        'name',
        'context',
        'is_active',
        'user_id',
        'user_type'
    ];
    
    protected $casts = [
        'context' => 'array',
        'is_active' => 'boolean',
    ];
    
    // Relationships
    public function user(): MorphTo
    public function logs(): HasMany
    
    // PersonaInterface implementation
    public function getId(): int|string
    public function getName(): string
    public function getContext(): array
    public function canAccess(string $resource, array $context = []): bool
    public function getUser(): ?Model
    public function isActive(): bool
    public function activate(): self
    public function deactivate(): self
    
    // Additional methods
    public function getSummary(): array
    public function hasPermission(string $permission): bool
    public function getRole(): ?string
    public function scopeActive(Builder $query): Builder
    public function scopeForUser(Builder $query, Model $user): Builder
}
```

### PersonaManager Service

```php
namespace Grazulex\LaravelMultiPersona\Services;

class PersonaManager
{
    public function __construct(
        protected Container $app,
        protected string $sessionKey = 'multipersona.active'
    ) {}
    
    /**
     * Get the currently active persona
     */
    public function current(): ?PersonaInterface
    
    /**
     * Set the active persona
     */
    public function setActive(PersonaInterface $persona): void
    
    /**
     * Clear the active persona
     */
    public function clear(): void
    
    /**
     * Check if there's an active persona
     */
    public function hasActive(): bool
    
    /**
     * Get all personas for a user
     */
    public function getPersonasForUser(Model $user): Collection
    
    /**
     * Create a new persona for a user
     */
    public function createPersona(Model $user, array $data): PersonaInterface
    
    /**
     * Switch to a different persona
     */
    public function switchTo(PersonaInterface $persona): void
    
    /**
     * Get persona by ID
     */
    public function find(int|string $id): ?PersonaInterface
    
    /**
     * Check if current persona has permission
     */
    public function can(string $permission, array $context = []): bool
    
    /**
     * Check if current persona has role
     */
    public function hasRole(string $role): bool
}
```

## Traits

### HasPersonas

```php
namespace Grazulex\LaravelMultiPersona\Traits;

trait HasPersonas
{
    /**
     * Get all personas for this user
     */
    public function personas(): MorphMany
    
    /**
     * Get the currently active persona
     */
    public function activePersona(): ?PersonaInterface
    
    /**
     * Check if user has an active persona
     */
    public function hasActivePersona(): bool
    
    /**
     * Create a new persona
     */
    public function createPersona(array $data): PersonaInterface
    
    /**
     * Switch to a specific persona
     */
    public function switchToPersona(PersonaInterface $persona): void
    
    /**
     * Get persona by name
     */
    public function getPersonaByName(string $name): ?PersonaInterface
    
    /**
     * Check if user has a persona with specific name
     */
    public function hasPersona(string $name): bool
    
    /**
     * Clear active persona
     */
    public function clearActivePersona(): void
    
    /**
     * Get personas with specific role
     */
    public function getPersonasByRole(string $role): Collection
    
    /**
     * Check if user has persona with specific role
     */
    public function hasPersonaWithRole(string $role): bool
}
```

## Events

### PersonaActivated

```php
namespace Grazulex\LaravelMultiPersona\Events;

class PersonaActivated
{
    public function __construct(
        protected PersonaInterface $persona,
        protected Model $user,
        protected array $context = []
    ) {}
    
    public function getPersona(): PersonaInterface
    public function getUser(): Model
    public function getContext(): array
    public function getSummary(): array
}
```

### PersonaSwitched

```php
namespace Grazulex\LaravelMultiPersona\Events;

class PersonaSwitched
{
    public function __construct(
        protected PersonaInterface $persona,
        protected Model $user,
        protected ?PersonaInterface $previousPersona = null,
        protected array $context = []
    ) {}
    
    public function getPersona(): PersonaInterface
    public function getUser(): Model
    public function getPreviousPersona(): ?PersonaInterface
    public function getContext(): array
    public function getSummary(): array
    public function isInitialActivation(): bool
}
```

### PersonaDeactivated

```php
namespace Grazulex\LaravelMultiPersona\Events;

class PersonaDeactivated
{
    public function __construct(
        protected PersonaInterface $persona,
        protected Model $user,
        protected array $context = []
    ) {}
    
    public function getPersona(): PersonaInterface
    public function getUser(): Model
    public function getContext(): array
    public function getSummary(): array
}
```

## Listeners

### LogPersonaSwitch

```php
namespace Grazulex\LaravelMultiPersona\Listeners;

class LogPersonaSwitch
{
    public function handle(PersonaSwitched $event): void
    
    private function formatLogMessage(array $summary): string
}
```

### CachePersonaPermissions

```php
namespace Grazulex\LaravelMultiPersona\Listeners;

class CachePersonaPermissions
{
    public function handle(PersonaActivated|PersonaSwitched $event): void
    
    protected function getCacheKey(Model $user, PersonaInterface $persona): string
    
    protected function getCacheTtl(): int
}
```

## Middleware

### EnsureActivePersona

```php
namespace Grazulex\LaravelMultiPersona\Middleware;

class EnsureActivePersona
{
    public function handle(Request $request, Closure $next, ?string $redirectRoute = null): Response
    
    protected function getRedirectRoute(?string $route): string
}
```

### SetPersonaFromRequest

```php
namespace Grazulex\LaravelMultiPersona\Middleware;

class SetPersonaFromRequest
{
    public function handle(Request $request, Closure $next, string $source = 'route'): Response
    
    protected function getPersonaIdFromRoute(Request $request): ?int
    
    protected function getPersonaIdFromQuery(Request $request): ?int
    
    protected function getPersonaIdFromHeader(Request $request): ?int
}
```

## Helper Functions

### Global Helpers

```php
/**
 * Get the currently active persona
 */
function persona(): ?PersonaInterface

/**
 * Get all personas for a user
 */
function personas(Model $user): Collection

/**
 * Check if current persona has permission
 */
function persona_can(string $permission, array $context = []): bool

/**
 * Check if current persona has role
 */
function persona_has_role(string $role): bool

/**
 * Get current persona context
 */
function persona_context(): array

/**
 * Get current persona role
 */
function persona_role(): ?string
```

## Configuration

### Configuration File Structure

```php
// config/multipersona.php
return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => env('MULTIPERSONA_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Personas Table
    |--------------------------------------------------------------------------
    */
    'table_name' => env('MULTIPERSONA_TABLE_NAME', 'personas'),

    /*
    |--------------------------------------------------------------------------
    | Default Permissions
    |--------------------------------------------------------------------------
    */
    'default_permissions' => [
        'read',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */
    'session_key' => 'multipersona.active',

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'permissions_ttl' => 3600, // 1 hour
        'prefix' => 'multipersona',
        'store' => null, // Use default cache store
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
    | Events Configuration
    |--------------------------------------------------------------------------
    */
    'events' => [
        'enabled' => true,
        'listeners' => [
            \Grazulex\LaravelMultiPersona\Listeners\LogPersonaSwitch::class,
            \Grazulex\LaravelMultiPersona\Listeners\CachePersonaPermissions::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-discovery Configuration
    |--------------------------------------------------------------------------
    */
    'auto_discovery' => [
        'enabled' => true,
        'providers' => [
            \Grazulex\LaravelMultiPersona\LaravelMultiPersonaServiceProvider::class,
        ],
    ],
];
```

### Environment Variables

```env
# User model configuration
MULTIPERSONA_USER_MODEL=App\\Models\\User

# Table configuration
MULTIPERSONA_TABLE_NAME=personas

# Cache configuration
MULTIPERSONA_CACHE_TTL=3600
MULTIPERSONA_CACHE_PREFIX=multipersona

# Session configuration
MULTIPERSONA_SESSION_KEY=multipersona.active
```

## Service Provider

### LaravelMultiPersonaServiceProvider

```php
namespace Grazulex\LaravelMultiPersona;

class LaravelMultiPersonaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../config/multipersona.php', 'multipersona');
        
        // Register singleton services
        $this->app->singleton('multipersona', function (Container $app): PersonaManager {
            return new PersonaManager($app);
        });
        
        $this->app->bind(PersonaInterface::class, function (Container $app): ?PersonaInterface {
            return $app['multipersona']->current();
        });
    }
    
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/multipersona.php' => config_path('multipersona.php'),
        ], 'multipersona-config');
        
        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'multipersona-migrations');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // Register middleware
        $this->registerMiddleware();
        
        // Register event listeners
        $this->registerEventListeners();
    }
    
    protected function registerMiddleware(): void
    protected function registerEventListeners(): void
}
```

## Database Schema

### Personas Table Migration

```php
Schema::create('personas', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->json('context');
    $table->boolean('is_active')->default(false);
    $table->morphs('user');
    $table->timestamps();
    
    $table->index(['user_id', 'user_type']);
    $table->index('is_active');
});
```

## Facade

### MultiPersona Facade

```php
namespace Grazulex\LaravelMultiPersona\Facades;

class MultiPersona extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'multipersona';
    }
}

// Usage
use Grazulex\LaravelMultiPersona\Facades\MultiPersona;

MultiPersona::current();
MultiPersona::setActive($persona);
MultiPersona::clear();
```

## Testing Helpers

### Persona Testing Trait

```php
namespace Grazulex\LaravelMultiPersona\Testing;

trait InteractsWithPersonas
{
    protected function actingAsPersona(PersonaInterface $persona): self
    
    protected function createPersonaForUser(Model $user, array $attributes = []): PersonaInterface
    
    protected function assertHasActivePersona(): self
    
    protected function assertPersonaIs(PersonaInterface $persona): self
    
    protected function assertPersonaHasPermission(string $permission): self
    
    protected function assertPersonaHasRole(string $role): self
}
```

## Exception Classes

```php
namespace Grazulex\LaravelMultiPersona\Exceptions;

class PersonaException extends Exception {}

class PersonaNotFoundException extends PersonaException {}

class InvalidPersonaException extends PersonaException {}

class PersonaPermissionException extends PersonaException {}

class PersonaActivationException extends PersonaException {}
```

## Artisan Commands

### Available Commands

```bash
# Clear all inactive personas
php artisan multipersona:clear-inactive

# List all personas for a user
php artisan multipersona:list-personas {user_id}

# Show persona statistics
php artisan multipersona:stats

# Cleanup expired temporary personas
php artisan multipersona:cleanup-expired
```

## Package Information

- **Version**: 1.0.0
- **PHP Requirements**: >= 8.3
- **Laravel Requirements**: >= 10.0
- **License**: MIT
- **Repository**: https://github.com/Grazulex/laravel-multipersona

## Migration Path

### Upgrading from Previous Versions

```php
// Add this to your migration file when upgrading
Schema::table('personas', function (Blueprint $table) {
    $table->timestamp('last_activated_at')->nullable();
    $table->json('metadata')->nullable();
});
```

This API reference provides complete documentation for all public methods, classes, and configurations available in Laravel MultiPersona.
