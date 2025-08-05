# Middleware Guide - Laravel MultiPersona

## Introduction

Laravel MultiPersona provides powerful middleware that allows you to protect routes, automatically set personas, and ensure proper context for your application.

## Available Middleware

### 1. EnsureActivePersona

Ensures that a user has an active persona before accessing protected routes.

```php
// Basic usage in routes
Route::middleware(['auth', 'persona.required'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/admin', [AdminController::class, 'index']);
});

// In controllers
class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'persona.required']);
    }
}
```

### 2. SetPersonaFromRequest

Automatically sets a persona based on request parameters.

```php
// Usage with route parameters
Route::middleware(['auth', 'persona.from_request'])->group(function () {
    Route::get('/company/{company_id}/dashboard', [CompanyController::class, 'dashboard']);
    Route::get('/tenant/{tenant_id}/admin', [TenantController::class, 'admin']);
});

// Usage with query parameters
Route::middleware(['auth', 'persona.from_request:query'])->group(function () {
    Route::get('/dashboard?persona_id=123', [DashboardController::class, 'index']);
});
```

## Custom Middleware Examples

### 1. Role-based Access Control

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $persona = persona();
        
        if (!$persona) {
            return redirect()->route('persona.select')
                ->with('error', 'Please select a persona to continue');
        }
        
        $context = $persona->getContext();
        $userRole = $context['role'] ?? 'guest';
        
        if ($userRole !== $role) {
            abort(403, "Access denied. Required role: {$role}");
        }
        
        return $next($request);
    }
}

// Registration in Kernel.php
protected $middlewareAliases = [
    'role' => \App\Http\Middleware\RequireRole::class,
];

// Usage in routes
Route::middleware(['auth', 'persona.required', 'role:admin'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
});
```

### 2. Company/Tenant Context Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        $persona = persona();
        
        if (!$persona) {
            return redirect()->route('persona.select');
        }
        
        $context = $persona->getContext();
        
        // Ensure persona has company context
        if (!isset($context['company_id'])) {
            abort(403, 'No company context available');
        }
        
        // Set company in request for easy access
        $request->merge(['current_company_id' => $context['company_id']]);
        
        // Share with views
        view()->share('currentCompany', $context['company_id']);
        
        return $next($request);
    }
}
```

### 3. Permission-based Access Control

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $persona = persona();
        
        if (!$persona) {
            return redirect()->route('persona.select');
        }
        
        if (!$persona->canAccess($permission)) {
            abort(403, "Permission denied: {$permission}");
        }
        
        return $next($request);
    }
}

// Usage
Route::middleware(['auth', 'persona.required', 'permission:manage_users'])
    ->get('/admin/users', [UserController::class, 'index']);
```

### 4. Multi-tenant Route Binding

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Company;

class BindTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $persona = persona();
        
        if (!$persona) {
            return redirect()->route('persona.select');
        }
        
        $context = $persona->getContext();
        $companyId = $context['company_id'] ?? null;
        
        if (!$companyId) {
            abort(403, 'No tenant context');
        }
        
        // Bind company to request
        $company = Company::findOrFail($companyId);
        $request->merge(['company' => $company]);
        
        // Set global scope for models
        app()->instance('current_company', $company);
        
        return $next($request);
    }
}
```

### 5. Dynamic Persona Selection

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AutoSelectPersona
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if (!$user || $user->hasActivePersona()) {
            return $next($request);
        }
        
        // Auto-select based on route
        $routeName = $request->route()->getName();
        $persona = $this->getPersonaForRoute($user, $routeName);
        
        if ($persona) {
            $user->switchToPersona($persona);
        }
        
        return $next($request);
    }
    
    private function getPersonaForRoute($user, string $routeName): ?object
    {
        $routePersonaMap = [
            'admin.*' => 'role:admin',
            'company.*' => 'role:manager',
            'client.*' => 'role:client',
        ];
        
        foreach ($routePersonaMap as $pattern => $requirement) {
            if (fnmatch($pattern, $routeName)) {
                [$key, $value] = explode(':', $requirement);
                
                return $user->personas()
                    ->whereJsonContains("context->{$key}", $value)
                    ->first();
            }
        }
        
        return null;
    }
}
```

## Middleware Groups

### 1. Admin Routes

```php
// In RouteServiceProvider or web.php
Route::middleware(['auth', 'persona.required', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::resource('users', UserController::class);
    Route::resource('companies', CompanyController::class);
});
```

### 2. Multi-tenant Routes

```php
Route::middleware(['auth', 'persona.required', 'company.context'])->prefix('company')->group(function () {
    Route::get('/dashboard', [CompanyController::class, 'dashboard']);
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/reports', [ReportController::class, 'index']);
});
```

### 3. API Routes with Persona

```php
Route::middleware(['auth:sanctum', 'persona.from_request'])->prefix('api')->group(function () {
    Route::get('/current-persona', [PersonaApiController::class, 'current']);
    Route::post('/switch-persona', [PersonaApiController::class, 'switch']);
    Route::get('/dashboard-data', [DashboardApiController::class, 'data']);
});
```

## Advanced Patterns

### 1. Conditional Middleware Application

```php
class ConditionalPersonaMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Only apply persona requirements for specific routes
        $protectedRoutes = [
            'admin.*',
            'company.*',
            'reports.*'
        ];
        
        $routeName = $request->route()->getName();
        $requiresPersona = collect($protectedRoutes)
            ->some(fn($pattern) => fnmatch($pattern, $routeName));
        
        if ($requiresPersona && !persona()) {
            return redirect()->route('persona.select');
        }
        
        return $next($request);
    }
}
```

### 2. Persona Context Injection

```php
class InjectPersonaContext
{
    public function handle(Request $request, Closure $next)
    {
        $persona = persona();
        
        if ($persona) {
            $context = $persona->getContext();
            
            // Inject into request
            $request->merge([
                'persona_context' => $context,
                'current_role' => $context['role'] ?? null,
                'current_company' => $context['company_id'] ?? null,
            ]);
            
            // Share with all views
            view()->share([
                'currentPersona' => $persona,
                'personaContext' => $context,
                'userRole' => $context['role'] ?? 'guest',
            ]);
        }
        
        return $next($request);
    }
}
```

### 3. Audit Trail Middleware

```php
class PersonaAuditMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $persona = persona();
        $user = auth()->user();
        
        if ($persona && $user) {
            $this->logPersonaActivity($request, $persona, $user);
        }
        
        return $next($request);
    }
    
    private function logPersonaActivity(Request $request, $persona, $user): void
    {
        $context = $persona->getContext();
        
        activity()
            ->causedBy($user)
            ->withProperties([
                'persona_id' => $persona->getId(),
                'persona_name' => $persona->getName(),
                'role' => $context['role'] ?? null,
                'company_id' => $context['company_id'] ?? null,
                'route' => $request->route()->getName(),
                'url' => $request->url(),
                'method' => $request->method(),
                'ip' => $request->ip(),
            ])
            ->log('persona_activity');
    }
}
```

## Global Middleware Registration

```php
// In app/Http/Kernel.php

protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \App\Http\Middleware\InjectPersonaContext::class,
    ],
    
    'api' => [
        // ... other middleware
        \App\Http\Middleware\PersonaAuditMiddleware::class,
    ],
];

protected $middlewareAliases = [
    // MultiPersona middleware
    'persona.required' => \Grazulex\LaravelMultiPersona\Middleware\EnsureActivePersona::class,
    'persona.from_request' => \Grazulex\LaravelMultiPersona\Middleware\SetPersonaFromRequest::class,
    
    // Custom middleware
    'role' => \App\Http\Middleware\RequireRole::class,
    'permission' => \App\Http\Middleware\RequirePermission::class,
    'company.context' => \App\Http\Middleware\EnsureCompanyContext::class,
    'tenant.bind' => \App\Http\Middleware\BindTenantContext::class,
];
```

## Best Practices

1. **Layer your middleware** - Start with authentication, then persona, then specific requirements
2. **Use meaningful redirects** - Always redirect to persona selection when no persona is active
3. **Share context with views** - Make persona data available globally when needed
4. **Log persona activities** - Track important actions for audit purposes
5. **Handle edge cases** - Consider what happens when personas become invalid
6. **Performance considerations** - Cache expensive permission checks

## Next Steps

- [Advanced Patterns](advanced-patterns.md)
- [Error Handling](error-handling.md)
- [Performance Optimization](performance.md)
