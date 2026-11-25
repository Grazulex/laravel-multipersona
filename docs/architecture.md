# Laravel MultiPersona - Architecture

## Overview

Laravel MultiPersona is a lightweight context-layer system that allows users to switch between different personas (roles, accounts, or tenants) dynamically without creating multiple logins or sessions.

## Core Components

### 1. PersonaInterface

The main contract that defines what a persona should implement:

```php
interface PersonaInterface
{
    public function getId(): int|string;
    public function getName(): string;
    public function getContext(): array;
    public function canAccess(string $resource, array $context = []): bool;
    public function getUser(): ?Model;
    public function isActive(): bool;
    public function activate(): self;
    public function deactivate(): self;
}
```

### 2. Persona Model

The Eloquent model that implements `PersonaInterface`:

- **Table**: `personas`
- **Fields**: `id`, `name`, `context` (JSON), `is_active`, `active_until`, `revert_to_persona_id`, `user_id`, `user_type`, `timestamps`
- **Relationships**: `belongsTo` User

### 3. PersonaManager Service

The main service class that handles persona operations:

- Set active persona
- Get current persona
- Switch between personas
- Clear active persona
- Check permissions

### 4. HasPersonas Trait

A trait for User models that provides:

- `personas()` relationship
- `createPersona()` method
- `switchToPersona()` method
- `hasPersona()` method
- `getPersonaByName()` method

### 5. Middleware

Two middleware classes for request handling:

- **EnsureActivePersona**: Ensures a persona is active
- **SetPersonaFromRequest**: Sets persona from request parameters

### 6. Gate Integration

`Authorization\PersonaGate` is an optional `Gate::before` callback (enabled with `gate.enabled`). It resolves the active persona of the checked user and grants the abilities its `canAccess()` accepts when the persona defines a `permissions` list. Other abilities are denied with `gate.deny_unlisted`, or left to the regular gates and policies. See [Usage Guide](usage-guide.md#4-gate-integration).

### 7. Data Filtering

`Traits\BelongsToPersona` is a trait for application models. It registers the `Scopes\PersonaScope` global scope, which restricts Eloquent queries to the rows whose persona column (`scope.column`, `persona_id` by default) matches the active persona: its ID, or a value of its context when `scope.context_key` is set. New records get the column filled from the active persona. Without active persona, queries are not filtered unless `scope.strict` is enabled. See [Usage Guide](usage-guide.md#5-data-filtering-per-persona).

### 8. Switch Audit

`Listeners\RecordPersonaSwitch` (registered when `audit.enabled` is true) writes every `PersonaSwitched` event to the audit table through the `Models\PersonaSwitchLog` model, then dispatches `Events\PersonaSwitchLogged`. `Listeners\LogPersonaSwitch` can send its log entries to a dedicated channel with `audit.log_channel`. See [Events Guide](events-guide.md#persona-switch-audit).

### 9. Time-limited Personas

`PersonaManager::setActiveFor()`, `PersonaManager::switchToFor()` and `HasPersonas::switchToPersonaFor()` activate a persona until a date stored in `personas.active_until`; `personas.revert_to_persona_id` keeps the persona that was active before. The expiration is enforced lazily: every read of the active persona (`PersonaManager::current()`, `HasPersonas::activePersona()`, `PersonaGate`) goes through `PersonaManager::enforceExpiration()`, which deactivates an expired persona with a conditional update (so that concurrent requests release it only once), activates the original persona again and dispatches `Events\PersonaExpired` after the regular activation events. `PersonaManager::releaseExpired()` does the same for every user and can be scheduled. See [Usage Guide](usage-guide.md#6-time-limited-personas).

## Data Flow

```
User Request → Middleware → PersonaManager → Session → Database
```

1. **Request arrives** with persona context
2. **Middleware** processes persona information
3. **PersonaManager** validates and sets active persona
4. **Database** persists the active persona (`is_active`, the source of truth)
5. **Session** keeps a pointer to the active persona ID, kept in sync with the database

## Database Schema

### personas table

```sql
CREATE TABLE personas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    context JSON NULL,
    is_active BOOLEAN DEFAULT FALSE,
    user_id BIGINT UNSIGNED NOT NULL,
    user_type VARCHAR(255) DEFAULT 'App\\Models\\User',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    active_until TIMESTAMP NULL,
    revert_to_persona_id BIGINT UNSIGNED NULL,
    INDEX idx_user_id_user_type (user_id, user_type),
    INDEX idx_user_id_is_active (user_id, is_active),
    INDEX idx_active_until (active_until)
);
```

`active_until` and `revert_to_persona_id` are added by a separate migration and are only set while a persona is active for a limited time.

### persona_switch_logs table

Audit of the persona switches (name configurable with `audit.table`):

```sql
CREATE TABLE persona_switch_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    user_type VARCHAR(255) NULL,
    from_persona_id BIGINT UNSIGNED NULL,
    from_persona_name VARCHAR(255) NULL,
    to_persona_id BIGINT UNSIGNED NULL,
    to_persona_name VARCHAR(255) NOT NULL,
    context JSON NULL,
    switched_at TIMESTAMP NOT NULL,
    INDEX idx_user_id_switched_at (user_id, switched_at),
    INDEX idx_from_persona_id (from_persona_id),
    INDEX idx_to_persona_id (to_persona_id)
);
```

## Context Structure

The `context` field stores JSON data that can include:

```json
{
    "role": "admin",
    "company_id": 123,
    "permissions": ["read", "write", "delete"],
    "department": "IT",
    "level": 5,
    "custom_data": {
        "theme": "dark",
        "language": "en"
    }
}
```

## Session Management

- The `is_active` column is the single source of truth for the active persona. A user has at most one active persona at a time.
- `Persona::activate()` deactivates the user's other personas in the same transaction; `PersonaManager::setActive()` and `HasPersonas::switchToPersona()` go through it, and `PersonaManager::clear()` deactivates the persona in the database.
- The session only keeps a pointer to the active persona ID under the key `active_persona_id`. When `PersonaManager::current()` resolves the persona, it checks the pointer against the database:
  - if the persona in the session is no longer active (another persona was activated elsewhere), the persona active for the same user is used and the session is updated;
  - if no persona is active anymore, the pointer is removed;
  - if the session holds no pointer, the active persona of the authenticated user is restored (for example after a new login).
- A persona whose `active_until` date has been reached is never returned: it is deactivated on the first read and the persona referenced by `revert_to_persona_id` becomes the active one. This is also checked for a persona already resolved by the manager, so long-running processes apply the expiration too.
- As a result, `PersonaManager::current()` and `HasPersonas::activePersona()` always agree.
- Persona data is lazy-loaded from database when needed

## Security Considerations

1. **Validation**: Always validate persona ownership before switching
2. **Permissions**: Check persona permissions before granting access
3. **Session Security**: Standard Laravel session security applies
4. **Database**: Use proper indexes for performance
5. **Data filtering**: `BelongsToPersona` does not filter queries when no persona is active unless `scope.strict` is enabled; it only covers Eloquent queries, not `DB::table()`
6. **High-privilege personas**: activate them for a limited time (`switchToPersonaFor()`) so that they do not stay active until the user leaves them. Code that reads the `is_active` column directly does not apply the expiration: use `persona()` / `activePersona()`, or schedule `PersonaManager::releaseExpired()`

## Extension Points

### Custom Persona Models

```php
class CustomPersona extends Model implements PersonaInterface
{
    // Custom implementation
}
```

### Custom Permission Logic

```php
public function canAccess(string $resource, array $context = []): bool
{
    // Custom permission logic
    return $this->customPermissionCheck($resource, $context);
}
```

### Custom Context Validation

```php
class PersonaManager
{
    public function setActive($persona): self
    {
        $this->validateCustomContext($persona);
        // ... rest of implementation
    }
}
```

## Performance Considerations

1. **Lazy Loading**: Persona data is only loaded when needed
2. **Caching**: Consider caching persona data for high-traffic applications
3. **Indexes**: Database indexes on user_id and is_active columns
4. **Session**: Minimal session storage (only persona ID)

## Testing Strategy

1. **Unit Tests**: Test PersonaManager functionality
2. **Feature Tests**: Test persona switching and permissions
3. **Integration Tests**: Test middleware and full request cycle
4. **Factory**: Use PersonaFactory for test data generation

## Configuration

Configuration is stored in `config/multipersona.php`:

- User model class
- Table name
- Session key
- Default permissions
- Middleware registration
- Gate integration (`gate.enabled`, `gate.deny_unlisted`)
- Persona data scope (`scope.column`, `scope.context_key`, `scope.strict`)
- Switch audit (`audit.enabled`, `audit.table`, `audit.log_channel`)
- Time-limited personas (`temporary.ttl`)

## Migration Path

For existing applications:

1. Install package
2. Publish and run migrations
3. Add HasPersonas trait to User model
4. Create initial personas for existing users
5. Add middleware to protected routes
6. Update policies and authorization logic
