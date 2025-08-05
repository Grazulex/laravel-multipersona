# Laravel MultiPersona

<img src="new_logo.png" alt="Laravel MultiPersona" width="200">

**Laravel MultiPersona** is a lightweight context-layer system for Laravel users.  

[![Latest Version](https://img.shields.io/packagist/v/grazulex/laravel-multipersona.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-multipersona)
[![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-multipersona.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-multipersona)
[![License](https://img.shields.io/github/license/grazulex/laravel-multipersona.svg?style=flat-square)](https://github.com/Grazulex/laravel-multipersona/blob/main/LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/grazulex/laravel-multipersona.svg?style=flat-square)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-12.x-ff2d20?style=flat-square&logo=laravel)](https://laravel.com/)
[![Tests](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-multipersona/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Grazulex/laravel-multipersona/actions)
[![Code Style](https://img.shields.io/badge/code%20style-pint-000000?style=flat-square&logo=laravel)](https://github.com/laravel/pint)

---

**Laravel MultiPersona** is a lightweight context-layer system for Laravel users.  
It allows a single user to switch between different **roles**, **accounts**, or **tenants** dynamically, without creating multiple logins or sessions.

---

## 🔍 What It Solves

- Switch between "personas" (admin ↔ client, company A ↔ company B)
- Contextual permissions and role handling
- No UI or API enforced – 100% backend, policy, and middleware-driven

## 📦 Installation

```bash
composer require grazulex/laravel-multipersona
```

## 🧩 Core Concepts

- **Persona**: A context attached to a user (e.g. company, role, project)
- **Active Persona**: The currently selected context
- **Persona Manager**: A service to query, switch, or manipulate personas
- **Middleware**: Force or apply a persona context

## ✅ Quick Example

```php
// Get current active persona
$currentPersona = persona();

// List all user personas
$userPersonas = auth()->user()->personas;

// Create a new persona
$persona = auth()->user()->createPersona([
    'name' => 'Company Admin',
    'context' => [
        'role' => 'admin',
        'company_id' => 123,
        'permissions' => ['read', 'write', 'delete']
    ]
]);

// Switch to persona
auth()->user()->switchToPersona($persona);
```

## 🧱 What's Provided

- **Trait**: `HasPersonas` for your `User` model
- **Middleware**: `EnsureActivePersona`, `SetPersonaFromRequest`
- **Helpers**: `persona()`, `personas($user)`
- **Events**: Complete event system for persona lifecycle
- **Service**: `PersonaManager` for programmatic access
- **Contract**: `PersonaInterface` for custom implementations

## ❌ What's Not Included

- No routes or controllers
- No CLI or HTTP APIs  
- No UI layer – you choose how to expose it

## 📚 Complete Documentation

### Getting Started
- [📖 Installation Guide](docs/installation-guide.md) - Complete setup instructions
- [� Usage Guide](docs/usage-guide.md) - Basic and advanced usage examples
- [⚡ Quick Start Example](examples/basic_usage.php) - Working code examples

### Core Features  
- [🎭 Events Guide](docs/events-guide.md) - Event system and listeners
- [🛡️ Middleware Guide](docs/middleware-guide.md) - Route protection and context
- [🏗️ Architecture](docs/architecture.md) - System design and components

### Advanced Topics
- [🎯 Advanced Patterns](docs/advanced-patterns.md) - Multi-tenant, role hierarchy, delegation
- [🌐 Frontend Integration](docs/frontend-integration.md) - Vue.js, React, Alpine.js examples
- [📋 API Reference](docs/api-reference.md) - Complete method documentation

### Use Cases
- **Multi-tenant SaaS**: Users switch between different company contexts
- **Role-based Access**: Same user, different permissions per context
- **Agency Management**: Manage multiple client accounts
- **Marketplace Platforms**: Buyer/seller context switching
- **Enterprise Systems**: Department or project-based access

## 🎯 Real-World Examples

### Multi-tenant Application
```php
// User switches between companies
$companyA = $user->createPersona([
    'name' => 'Acme Corp Admin',
    'context' => [
        'company_id' => 1,
        'role' => 'admin',
        'permissions' => ['manage_users', 'view_reports']
    ]
]);

$companyB = $user->createPersona([
    'name' => 'TechStart User', 
    'context' => [
        'company_id' => 2,
        'role' => 'user',
        'permissions' => ['view_dashboard']
    ]
]);
```

### Middleware Protection
```php
// Protect routes requiring specific roles
Route::middleware(['auth', 'persona.required', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});
```

### Frontend Integration
```vue
<!-- Vue.js component -->
<PersonaSelector 
    :current-persona="currentPersona"
    :available-personas="availablePersonas"
    @persona-changed="handlePersonaChange"
/>
```

## 🧪 Testing

The package includes comprehensive test coverage:

```bash
composer test
```

Current test metrics:
- **58 tests** across Unit, Feature, Integration, and Listeners
- **87% code coverage**
- **149 assertions** ensuring reliability

## 🤝 Contributing

We welcome contributions! See our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup
```bash
git clone https://github.com/grazulex/laravel-multipersona.git
cd laravel-multipersona
composer install
composer test
```

## 🔒 Security

If you discover any security vulnerabilities, please review our [Security Policy](SECURITY.md).

## 📄 License

Laravel MultiPersona is open-sourced software licensed under the [MIT license](LICENSE.md).

---

<div align="center">
  <p>Made with ❤️ for the Laravel community</p>
  <p>
    <a href="https://github.com/grazulex/laravel-multipersona/wiki">📖 Documentation</a> •
    <a href="https://github.com/grazulex/laravel-multipersona/issues">🐛 Report Issues</a> •
    <a href="https://github.com/grazulex/laravel-multipersona/discussions">💬 Discussions</a>
  </p>
</div>
---

## 🤝 Contributing

We welcome contributions! See our [Contributing Guide](CONTRIBUTING.md).

---

<div align="center">
  <p>Made with ❤️ for the Laravel community</p>
  <p>
    <a href="https://github.com/grazulex/laravel-multipersona/wiki">📖 Documentation</a> •
    <a href="https://github.com/grazulex/laravel-multipersona/issues">🐛 Report Issues</a> •
    <a href="https://github.com/grazulex/laravel-multipersona/discussions">💬 Discussions</a>
  </p>
</div>