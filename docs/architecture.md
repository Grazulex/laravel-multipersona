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
- **Fields**: `id`, `name`, `context` (JSON), `is_active`, `user_id`, `user_type`, `timestamps`
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

## Data Flow

```
User Request → Middleware → PersonaManager → Session → Database
```

1. **Request arrives** with persona context
2. **Middleware** processes persona information
3. **PersonaManager** validates and sets active persona
4. **Session** stores active persona ID
5. **Database** retrieves persona data when needed

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
    INDEX idx_user_id_user_type (user_id, user_type),
    INDEX idx_user_id_is_active (user_id, is_active)
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

- Active persona ID is stored in session with key `active_persona_id`
- Session is automatically managed by PersonaManager
- Persona data is lazy-loaded from database when needed

## Security Considerations

1. **Validation**: Always validate persona ownership before switching
2. **Permissions**: Check persona permissions before granting access
3. **Session Security**: Standard Laravel session security applies
4. **Database**: Use proper indexes for performance

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

## Migration Path

For existing applications:

1. Install package
2. Publish and run migrations
3. Add HasPersonas trait to User model
4. Create initial personas for existing users
5. Add middleware to protected routes
6. Update policies and authorization logic
