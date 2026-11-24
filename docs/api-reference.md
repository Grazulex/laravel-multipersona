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
    public function activate(): self   // sets is_active and deactivates the user's other personas
    public function deactivate(): self // clears is_active
    
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
     * Get the currently active persona.
     * Resolved from the session pointer and checked against the
     * `is_active` column (the source of truth); falls back to the
     * authenticated user's active persona.
     */
    public function current(): ?PersonaInterface
    
    /**
     * Set the active persona.
     * Persists it (`is_active`), deactivates the user's other personas
     * and stores its ID in the session.
     */
    public function setActive(PersonaInterface $persona): void
    
    /**
     * Clear the active persona.
     * Deactivates it in the database and removes the session pointer.
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
     * Get the persona switches recorded for this user (audit enabled)
     */
    public function personaSwitchLogs(): HasMany
    
    /**
     * Get the currently active persona (same result as PersonaManager::current()
     * for this user, both read the `is_active` column)
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

### BelongsToPersona

```php
namespace Grazulex\LaravelMultiPersona\Traits;

trait BelongsToPersona
{
    /**
     * Registers the PersonaScope global scope and fills the persona column
     * of new records from the active persona
     */
    public static function bootBelongsToPersona(): void

    /**
     * Column referencing the persona (default: config scope.column)
     */
    public function getPersonaColumn(): string

    /**
     * Key of the persona context compared with the column, or null to
     * compare with the persona ID (default: config scope.context_key)
     */
    public function getPersonaContextKey(): ?string

    /**
     * Persona column prefixed with the table name
     */
    public function getQualifiedPersonaColumn(): string

    /**
     * Owning persona (when the column holds the persona ID)
     */
    public function persona(): BelongsTo

    /**
     * Model::withoutPersonaScope(): query without the persona filter
     */
    public function scopeWithoutPersonaScope(Builder $query): Builder

    /**
     * Model::forPersona($persona): rows of the given persona (instance) or
     * rows whose column equals the given scalar
     */
    public function scopeForPersona(Builder $query, PersonaInterface|int|string $persona): Builder
}
```

For models of your application (not the `User` model). See [Usage Guide](usage-guide.md#5-data-filtering-per-persona).

## Scopes

### PersonaScope

```php
namespace Grazulex\LaravelMultiPersona\Scopes;

class PersonaScope implements Scope
{
    public const string DEFAULT_COLUMN = 'persona_id';

    // Column referencing the persona for the model
    public static function column(Model $model): string

    // Context key compared with the column, or null for the persona ID
    public static function contextKey(Model $model): ?string

    // Value the column must hold for a row to belong to the persona
    public static function valueFor(PersonaInterface $persona, ?string $contextKey = null): int|string|null

    // Persona currently active (PersonaManager::current())
    public static function activePersona(): ?PersonaInterface

    // Value of scope.strict
    public static function isStrict(): bool

    public function apply(Builder $builder, Model $model): void
}
```

Global scope added by `BelongsToPersona`. With an active persona, the query is restricted to the rows whose column matches the persona; a persona without value for the context key matches no row. Without active persona, the query is not filtered unless `scope.strict` is `true`, in which case it returns no row.

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

### PersonaSwitchLogged

```php
namespace Grazulex\LaravelMultiPersona\Events;

class PersonaSwitchLogged
{
    public function __construct(
        public readonly PersonaSwitchLog $log,
        public readonly mixed $user = null
    ) {}

    public function getLog(): PersonaSwitchLog
    public function getUser(): mixed
}
```

Dispatched by `RecordPersonaSwitch` once a switch has been written to the audit table.

## Listeners

### LogPersonaSwitch

```php
namespace Grazulex\LaravelMultiPersona\Listeners;

class LogPersonaSwitch implements ShouldQueue
{
    public function handle(PersonaSwitched $event): void
}
```

Registered automatically on `PersonaSwitched` (see `register_listeners`). Writes to the `audit.log_channel` log channel when it is set, otherwise to the default channel.

### RecordPersonaSwitch

```php
namespace Grazulex\LaravelMultiPersona\Listeners;

class RecordPersonaSwitch
{
    // Creates a PersonaSwitchLog entry, then dispatches PersonaSwitchLogged
    public function handle(PersonaSwitched $event): void
}
```

Runs synchronously and is registered on `PersonaSwitched` when `audit.enabled` is `true` (independently of `register_listeners`). See [Events Guide](events-guide.md#persona-switch-audit).

### CachePersonaPermissions

```php
namespace Grazulex\LaravelMultiPersona\Listeners;

class CachePersonaPermissions implements ShouldQueue
{
    public const CACHE_PREFIX = 'multipersona:permissions';
    public const TTL = 3600; // seconds

    public function handle(PersonaActivated $event): void

    // "multipersona:permissions:{user_id}:{persona_id}"
    public static function cacheKey(mixed $user, PersonaInterface|int|string $persona): string

    // Cached entry (persona_id, persona_name, context, permissions, role, cached_at) or null
    public static function cached(mixed $user, PersonaInterface|int|string $persona): ?array

    public static function forget(mixed $user, PersonaInterface|int|string $persona): void
}
```

Registered automatically on `PersonaActivated`. `$user` can be a model, an object with an `id` property, or the user identifier.

### ForgetPersonaPermissions

```php
namespace Grazulex\LaravelMultiPersona\Listeners;

class ForgetPersonaPermissions
{
    public function handle(PersonaDeactivated|PersonaSwitched $event): void

    // Forgets the cache of the deactivated persona
    public function handleDeactivated(PersonaDeactivated $event): void

    // Forgets the cache of the previous persona (unless it is the same persona)
    public function handleSwitched(PersonaSwitched $event): void
}
```

Runs synchronously and is registered automatically on `PersonaSwitched` and `PersonaDeactivated`. The service provider also forgets the cache of a persona when the `Persona` model is updated or deleted.

## Authorization

### PersonaGate

```php
namespace Grazulex\LaravelMultiPersona\Authorization;

class PersonaGate
{
    public function __construct(PersonaManager $manager)

    // Registers before() as a Gate::before callback
    public static function register(Gate $gate): void

    // true = grant, false = deny, null = defer to the regular gates and policies
    public function before(mixed $user, string $ability, array $arguments = []): ?bool

    // Active persona of the user: the PersonaManager's persona when it belongs
    // to the user, otherwise the user's active persona read from the database
    public function personaFor(Authenticatable $user): ?PersonaInterface
}
```

Registered automatically when `gate.enabled` is `true`. When the user's active persona has a `permissions` list, an ability accepted by `canAccess()` is granted; other abilities are denied if `gate.deny_unlisted` is `true`, otherwise deferred. See [Usage Guide](usage-guide.md#4-gate-integration).

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
    // Registers LogPersonaSwitch, CachePersonaPermissions and
    // ForgetPersonaPermissions, and forgets the permissions cache of a
    // persona when it is updated or deleted
    'register_listeners' => true,

    /*
    |--------------------------------------------------------------------------
    | Gate Integration
    |--------------------------------------------------------------------------
    */
    // Evaluates Gate abilities in the context of the active persona
    // (see PersonaGate). Disabled by default.
    'gate' => [
        'enabled' => false,
        'deny_unlisted' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Persona Data Scope
    |--------------------------------------------------------------------------
    */
    // Settings of the BelongsToPersona trait: column referencing the
    // persona, optional key of the persona context compared with it (null =
    // persona ID), and strict mode (no row without active persona).
    'scope' => [
        'column' => 'persona_id',
        'context_key' => null,
        'strict' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Persona Switch Audit
    |--------------------------------------------------------------------------
    */
    // enabled: records every switch in the audit table (RecordPersonaSwitch)
    // table: name of the audit table
    // log_channel: log channel of LogPersonaSwitch (null = default channel)
    'audit' => [
        'enabled' => false,
        'table' => 'persona_switch_logs',
        'log_channel' => null,
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
        
        // Register the bundled listeners (unless register_listeners is false)
        $this->registerListeners();

        // Register RecordPersonaSwitch (when audit.enabled is true)
        $this->registerAudit();

        // Register the PersonaGate Gate::before callback (when gate.enabled is true)
        $this->registerGate();
    }
    
    private function registerMiddleware(): void
    private function registerListeners(): void
    private function registerAudit(): void
    private function registerGate(): void
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

### Persona Switch Logs Table Migration

```php
Schema::create(config('multipersona.audit.table', 'persona_switch_logs'), function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->string('user_type')->nullable();
    $table->unsignedBigInteger('from_persona_id')->nullable();
    $table->string('from_persona_name')->nullable();
    $table->unsignedBigInteger('to_persona_id')->nullable();
    $table->string('to_persona_name');
    $table->json('context')->nullable();
    $table->timestamp('switched_at');

    $table->index(['user_id', 'switched_at']);
    $table->index('from_persona_id');
    $table->index('to_persona_id');
});
```

### PersonaSwitchLog Model

```php
namespace Grazulex\LaravelMultiPersona\Models;

class PersonaSwitchLog extends Model
{
    public function fromPersona(): BelongsTo   // previous persona
    public function toPersona(): BelongsTo     // new persona
    public function user(): BelongsTo          // configured user model

    // PersonaSwitchLog::forUser($user): switches of a user (model or key)
    public function scopeForUser(Builder $query, Model|int|string $user): Builder

    // PersonaSwitchLog::forPersona($persona): switches from or to a persona
    public function scopeForPersona(Builder $query, Persona|int|string $persona): Builder
}
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
- **PHP Requirements**: >= 8.4
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
