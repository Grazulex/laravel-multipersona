# Laravel MultiPersona – Technical Overview

## 🧠 Architecture

- The core component is the `PersonaManager` service.
- Users can have many `Personas` (morph relation).
- Each `Persona` can represent a role, tenant, account, etc.
- The active persona is stored in the current request context (session, header, etc).

## 🧩 Service: PersonaManagerInterface

```php
interface PersonaManagerInterface {
    public function getAllForUser(User $user): Collection;
    public function getActive(): ?PersonaInterface;
    public function setActive(PersonaInterface $persona): void;
    public function add(User $user, array $attributes): PersonaInterface;
    public function remove(PersonaInterface $persona): void;
    public function assignRole(PersonaInterface $persona, string $role): void;
}
```

## 🧰 Middleware

- `EnsureActivePersona` – blocks access if no persona is active
- `SetPersonaFromRequest` – activates persona via session/header/query

## 🔁 Extending

You may bind your own `PersonaInterface` implementation in the service container.
