# Events Guide - Laravel MultiPersona

## Introduction

Laravel MultiPersona integrates a complete event system that allows you to react to persona changes. This is particularly useful for auditing, caching, or other business logic.

## Available Events

### 1. PersonaActivated

Triggered when a persona is activated for the first time.

```php
use Grazulex\LaravelMultiPersona\Events\PersonaActivated;

// The event contains:
$event->getPersona();     // The activated persona
$event->getUser();        // The user
$event->getContext();     // Activation context
$event->getSummary();     // Complete event summary
```

### 2. PersonaSwitched

Triggered when switching from one persona to another.

```php
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;

// The event contains:
$event->getPersona();         // The new persona
$event->getPreviousPersona(); // The previous persona (can be null)
$event->getUser();            // The user
$event->getContext();         // Switch context
$event->getSummary();         // Complete event summary
```

### 3. PersonaDeactivated

Triggered when a persona is deactivated.

```php
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;

// The event contains:
$event->getPersona();     // The deactivated persona
$event->getUser();        // The user
$event->getContext();     // Deactivation context
$event->getSummary();     // Complete event summary
```

## Provided Listeners

### 1. LogPersonaSwitch

Automatically logs all persona changes in Laravel logs.

```php
// The listener is automatically configured and generates logs like:
// [INFO] Persona switched: {"user_id": 1, "from_persona": "Admin", "to_persona": "User", ...}
```

### 2. CachePersonaPermissions

Automatically caches persona permissions to improve performance.

```php
// Cache is automatically managed with keys:
// "multipersona:permissions:{user_id}:{persona_id}"
```

## Creating Custom Listeners

### 1. Simple Listener

```php
<?php

namespace App\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyPersonaActivation implements ShouldQueue
{
    public function handle(PersonaActivated $event): void
    {
        $persona = $event->getPersona();
        $user = $event->getUser();
        $summary = $event->getSummary();
        
        // Notify other users
        if ($summary['new_persona']['context']['role'] === 'admin') {
            $this->notifyAdminActivation($user, $persona);
        }
        
        // Update metrics
        $this->updateMetrics($summary);
    }
    
    private function notifyAdminActivation($user, $persona)
    {
        // Send notification
        // Mail::to('security@company.com')->send(new AdminActivated($user, $persona));
    }
    
    private function updateMetrics($summary)
    {
        // Increment switch counters
        // Redis::incr('persona_activations_count');
    }
}
```

### 2. Listener with Complete Audit

```php
<?php

namespace App\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use App\Models\AuditLog;

class AuditPersonaChanges
{
    public function handle(PersonaSwitched $event): void
    {
        $summary = $event->getSummary();
        
        AuditLog::create([
            'user_id' => $summary['user']['id'],
            'action' => 'persona_switched',
            'details' => [
                'from_persona' => $summary['previous_persona']['name'] ?? null,
                'to_persona' => $summary['new_persona']['name'],
                'from_role' => $summary['previous_persona']['context']['role'] ?? null,
                'to_role' => $summary['new_persona']['context']['role'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => $summary['timestamp'],
                'is_initial_activation' => $summary['is_initial_activation'],
            ],
        ]);
    }
}
```

### 3. Listener for Advanced Caching

```php
<?php

namespace App\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Illuminate\Support\Facades\Cache;

class CacheUserPermissions
{
    public function handleActivation(PersonaActivated $event): void
    {
        $persona = $event->getPersona();
        $user = $event->getUser();
        $context = $persona->getContext();
        
        // Cache detailed permissions
        $cacheKey = "user_permissions_{$user->getKey()}";
        $permissions = $this->buildPermissionSet($context);
        
        Cache::put($cacheKey, $permissions, now()->addHours(2));
        
        // Cache relations
        if (isset($context['company_id'])) {
            $this->cacheCompanyRelations($user, $context['company_id']);
        }
    }
    
    public function handleDeactivation(PersonaDeactivated $event): void
    {
        $user = $event->getUser();
        
        // Clear all user-related caches
        Cache::forget("user_permissions_{$user->getKey()}");
        Cache::tags(['user_' . $user->getKey()])->flush();
    }
    
    private function buildPermissionSet(array $context): array
    {
        $permissions = $context['permissions'] ?? [];
        $role = $context['role'] ?? 'guest';
        
        // Add role-based permissions
        $rolePermissions = config("permissions.roles.{$role}", []);
        
        return array_unique(array_merge($permissions, $rolePermissions));
    }
    
    private function cacheCompanyRelations($user, $companyId): void
    {
        // Cache company relations
        $companyData = [
            'company_id' => $companyId,
            'user_id' => $user->getKey(),
            'cached_at' => now(),
        ];
        
        Cache::tags(['company_' . $companyId, 'user_' . $user->getKey()])
             ->put("company_relation_{$user->getKey()}_{$companyId}", $companyData, now()->addHours(4));
    }
}
```

## Listener Registration

### 1. In EventServiceProvider

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PersonaActivated::class => [
            \App\Listeners\NotifyPersonaActivation::class,
            \App\Listeners\CacheUserPermissions::class . '@handleActivation',
        ],
        
        PersonaSwitched::class => [
            \App\Listeners\AuditPersonaChanges::class,
        ],
        
        PersonaDeactivated::class => [
            \App\Listeners\CacheUserPermissions::class . '@handleDeactivation',
        ],
    ];
}
```

### 2. Auto-discovery with PHP 8 Attributes

```php
<?php

namespace App\Listeners;

use Illuminate\Events\Listener;
use Grazulex\LaravelMultiPersona\Events\PersonaActivated;

#[Listener]
class UpdateUserActivity
{
    public function handle(PersonaActivated $event): void
    {
        $user = $event->getUser();
        
        $user->update([
            'last_persona_activation' => now(),
            'last_activity' => now(),
        ]);
    }
}
```

## Advanced Usage Examples

### 1. Real-time Notification System

```php
<?php

namespace App\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use App\Events\UserPersonaChanged;

class BroadcastPersonaChanges
{
    public function handle(PersonaSwitched $event): void
    {
        $user = $event->getUser();
        $summary = $event->getSummary();
        
        // Broadcast event via WebSockets
        broadcast(new UserPersonaChanged(
            $user->getKey(),
            $summary['new_persona']['name'],
            $summary['new_persona']['context']['role'] ?? null
        ))->toOthers();
    }
}
```

### 2. External System Integration

```php
<?php

namespace App\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Illuminate\Support\Facades\Http;

class SyncWithExternalSystem
{
    public function handle(PersonaActivated $event): void
    {
        $persona = $event->getPersona();
        $user = $event->getUser();
        $context = $persona->getContext();
        
        // Sync with external CRM
        if (isset($context['company_id'])) {
            $this->syncWithCRM($user, $context);
        }
        
        // Update external permission system
        if (isset($context['role']) && $context['role'] === 'admin') {
            $this->grantExternalPermissions($user, $context);
        }
    }
    
    private function syncWithCRM($user, $context): void
    {
        Http::post('https://crm.example.com/api/user-context', [
            'user_id' => $user->getKey(),
            'company_id' => $context['company_id'],
            'role' => $context['role'] ?? 'user',
            'permissions' => $context['permissions'] ?? [],
        ]);
    }
    
    private function grantExternalPermissions($user, $context): void
    {
        // Sync with external permission system
        Http::put("https://permissions.example.com/api/users/{$user->getKey()}", [
            'roles' => [$context['role']],
            'permissions' => $context['permissions'] ?? [],
        ]);
    }
}
```

### 3. Metrics and Analytics

```php
<?php

namespace App\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Illuminate\Support\Facades\Redis;

class TrackPersonaMetrics
{
    public function handle(PersonaSwitched $event): void
    {
        $summary = $event->getSummary();
        
        // Redis counters
        Redis::incr('persona:switches:total');
        Redis::incr('persona:switches:' . date('Y-m-d'));
        
        $role = $summary['new_persona']['context']['role'] ?? 'unknown';
        Redis::incr("persona:switches:role:{$role}");
        
        // Session time per persona
        if (!$summary['is_initial_activation'] && $summary['previous_persona']) {
            $sessionTime = now()->diffInMinutes($summary['timestamp']);
            Redis::lpush('persona:session_times', $sessionTime);
            Redis::ltrim('persona:session_times', 0, 999); // Keep last 1000
        }
        
        // Advanced analytics
        $this->trackUserBehavior($summary);
    }
    
    private function trackUserBehavior(array $summary): void
    {
        $analyticsData = [
            'event' => 'persona_switched',
            'user_id' => $summary['user']['id'],
            'from_role' => $summary['previous_persona']['context']['role'] ?? null,
            'to_role' => $summary['new_persona']['context']['role'] ?? null,
            'timestamp' => $summary['timestamp'],
        ];
        
        // Send to Google Analytics, Mixpanel, etc.
        // Analytics::track($analyticsData);
    }
}
```

## Available Data Summary

Each event provides a `getSummary()` method that returns a structured array:

```php
$summary = $event->getSummary();

// Summary structure:
[
    'user' => [
        'id' => 1,
        'type' => 'App\\Models\\User',
    ],
    'new_persona' => [
        'id' => 2,
        'name' => 'Administrator',
        'context' => [
            'role' => 'admin',
            'permissions' => ['read', 'write', 'delete'],
        ],
    ],
    'previous_persona' => [ // Only for PersonaSwitched
        'id' => 1,
        'name' => 'User',
        'context' => [...],
    ],
    'is_initial_activation' => false, // Only for PersonaSwitched
    'timestamp' => '2025-08-05 15:30:00',
    'context' => [ // Activation/switch context
        'method' => 'setActive',
        'ip' => '192.168.1.1',
        // Other contextual data
    ],
]
```

## Best Practices

1. **Use queues** for heavy listeners
2. **Handle exceptions** to avoid interrupting the process
3. **Limit operations** in synchronous listeners
4. **Use cache** intelligently to avoid overloads
5. **Audit sensitive changes** for security

## Next Steps

- [Middleware Guide](middleware-guide.md)
- [Advanced Patterns](advanced-patterns.md)
- [Error Handling](error-handling.md)
