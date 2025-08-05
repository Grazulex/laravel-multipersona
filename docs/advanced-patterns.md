# Advanced Patterns - Laravel MultiPersona

## Introduction

This guide covers advanced usage patterns and architectural approaches for implementing complex scenarios with Laravel MultiPersona.

## Pattern 1: Multi-Tenant SaaS Application

### Architecture Overview

```php
// Tenant Model
class Tenant extends Model
{
    protected $fillable = ['name', 'domain', 'settings'];
    
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'permissions');
    }
}

// Enhanced User Model
class User extends Model
{
    use HasPersonas;
    
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role', 'permissions');
    }
    
    public function createTenantPersona(Tenant $tenant, string $role, array $permissions = [])
    {
        return $this->createPersona([
            'name' => "{$role} @ {$tenant->name}",
            'context' => [
                'tenant_id' => $tenant->id,
                'role' => $role,
                'permissions' => $permissions,
                'tenant_settings' => $tenant->settings ?? [],
            ]
        ]);
    }
}
```

### Automatic Persona Creation

```php
class TenantPersonaService
{
    public function createPersonasForUser(User $user, Tenant $tenant, string $role): void
    {
        $permissions = $this->getPermissionsForRole($role, $tenant);
        
        $user->createTenantPersona($tenant, $role, $permissions);
        
        // Create audit log
        AuditLog::create([
            'action' => 'tenant_persona_created',
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'details' => compact('role', 'permissions'),
        ]);
    }
    
    private function getPermissionsForRole(string $role, Tenant $tenant): array
    {
        $basePermissions = config("permissions.roles.{$role}", []);
        $tenantPermissions = $tenant->settings['role_permissions'][$role] ?? [];
        
        return array_unique(array_merge($basePermissions, $tenantPermissions));
    }
}
```

### Tenant Context Middleware

```php
class TenantContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $persona = persona();
        
        if (!$persona) {
            return redirect()->route('tenant.select');
        }
        
        $context = $persona->getContext();
        $tenantId = $context['tenant_id'] ?? null;
        
        if (!$tenantId) {
            abort(403, 'No tenant context available');
        }
        
        $tenant = Tenant::findOrFail($tenantId);
        
        // Set global tenant context
        app()->instance('current_tenant', $tenant);
        config(['app.current_tenant_id' => $tenantId]);
        
        // Apply tenant-specific database connection if needed
        $this->switchDatabaseConnection($tenant);
        
        return $next($request);
    }
    
    private function switchDatabaseConnection(Tenant $tenant): void
    {
        if ($tenant->database_name) {
            config([
                'database.connections.tenant' => array_merge(
                    config('database.connections.mysql'),
                    ['database' => $tenant->database_name]
                )
            ]);
            
            DB::setDefaultConnection('tenant');
        }
    }
}
```

## Pattern 2: Role Hierarchy System

### Hierarchical Role Implementation

```php
class RoleHierarchy
{
    protected array $hierarchy = [
        'super_admin' => ['admin', 'manager', 'user', 'guest'],
        'admin' => ['manager', 'user', 'guest'],
        'manager' => ['user', 'guest'],
        'user' => ['guest'],
        'guest' => [],
    ];
    
    public function hasRole(string $userRole, string $requiredRole): bool
    {
        if ($userRole === $requiredRole) {
            return true;
        }
        
        return in_array($requiredRole, $this->hierarchy[$userRole] ?? []);
    }
    
    public function getInheritedPermissions(string $role): array
    {
        $permissions = [];
        $roles = [$role, ...$this->hierarchy[$role] ?? []];
        
        foreach ($roles as $r) {
            $rolePermissions = config("permissions.roles.{$r}", []);
            $permissions = array_merge($permissions, $rolePermissions);
        }
        
        return array_unique($permissions);
    }
}

// Enhanced Persona Model
class Persona extends Model implements PersonaInterface
{
    public function canAccess(string $resource, array $context = []): bool
    {
        $personaContext = $this->getContext();
        $role = $personaContext['role'] ?? 'guest';
        
        $hierarchy = app(RoleHierarchy::class);
        $permissions = $hierarchy->getInheritedPermissions($role);
        
        return in_array($resource, $permissions);
    }
    
    public function hasRole(string $role): bool
    {
        $currentRole = $this->getContext()['role'] ?? 'guest';
        
        return app(RoleHierarchy::class)->hasRole($currentRole, $role);
    }
}
```

## Pattern 3: Time-Based Personas

### Temporary Access Implementation

```php
class TemporaryPersonaService
{
    public function grantTemporaryAccess(
        User $user, 
        string $role, 
        array $permissions, 
        Carbon $expiresAt,
        string $reason = null
    ): Persona {
        $persona = $user->createPersona([
            'name' => "Temporary {$role}",
            'context' => [
                'role' => $role,
                'permissions' => $permissions,
                'temporary' => true,
                'expires_at' => $expiresAt->toISOString(),
                'granted_by' => auth()->id(),
                'reason' => $reason,
            ]
        ]);
        
        // Schedule cleanup
        $this->scheduleCleanup($persona, $expiresAt);
        
        return $persona;
    }
    
    private function scheduleCleanup(Persona $persona, Carbon $expiresAt): void
    {
        dispatch(new CleanupTemporaryPersona($persona->id))
            ->delay($expiresAt);
    }
}

// Middleware to check expiration
class CheckPersonaExpiration
{
    public function handle(Request $request, Closure $next)
    {
        $persona = persona();
        
        if ($persona && $this->isExpired($persona)) {
            $persona->deactivate();
            app('multipersona')->clear();
            
            return redirect()->route('persona.expired')
                ->with('message', 'Your temporary access has expired');
        }
        
        return $next($request);
    }
    
    private function isExpired(PersonaInterface $persona): bool
    {
        $context = $persona->getContext();
        
        if (!($context['temporary'] ?? false)) {
            return false;
        }
        
        $expiresAt = $context['expires_at'] ?? null;
        
        return $expiresAt && Carbon::parse($expiresAt)->isPast();
    }
}
```

## Pattern 4: Delegated Access

### Delegation System

```php
class PersonaDelegationService
{
    public function delegatePersona(
        User $delegator,
        User $delegate,
        Persona $persona,
        array $restrictedPermissions = [],
        ?Carbon $expiresAt = null
    ): Persona {
        $originalContext = $persona->getContext();
        
        // Remove restricted permissions
        $permissions = array_diff(
            $originalContext['permissions'] ?? [],
            $restrictedPermissions
        );
        
        $delegatedPersona = $delegate->createPersona([
            'name' => "Delegated: {$persona->getName()}",
            'context' => array_merge($originalContext, [
                'delegated_from' => $delegator->id,
                'original_persona_id' => $persona->getId(),
                'permissions' => $permissions,
                'delegation_expires_at' => $expiresAt?->toISOString(),
                'restrictions' => $restrictedPermissions,
            ])
        ]);
        
        // Log delegation
        activity()
            ->causedBy($delegator)
            ->performedOn($delegatedPersona)
            ->withProperties([
                'delegate_user_id' => $delegate->id,
                'original_persona_id' => $persona->getId(),
                'restricted_permissions' => $restrictedPermissions,
            ])
            ->log('persona_delegated');
            
        return $delegatedPersona;
    }
    
    public function revokeDelegation(Persona $delegatedPersona): void
    {
        $context = $delegatedPersona->getContext();
        
        if (!isset($context['delegated_from'])) {
            throw new InvalidArgumentException('Not a delegated persona');
        }
        
        $delegatedPersona->deactivate();
        
        activity()
            ->causedBy(auth()->user())
            ->performedOn($delegatedPersona)
            ->log('persona_delegation_revoked');
    }
}
```

## Pattern 5: Context-Aware Permissions

### Dynamic Permission System

```php
class ContextualPermissionService
{
    public function checkPermission(string $permission, array $context = []): bool
    {
        $persona = persona();
        
        if (!$persona) {
            return false;
        }
        
        $personaContext = $persona->getContext();
        
        // Base permission check
        if (!in_array($permission, $personaContext['permissions'] ?? [])) {
            return false;
        }
        
        // Context-specific checks
        return $this->checkContextualRules($permission, $personaContext, $context);
    }
    
    private function checkContextualRules(
        string $permission, 
        array $personaContext, 
        array $requestContext
    ): bool {
        $rules = config("permissions.contextual_rules.{$permission}", []);
        
        foreach ($rules as $rule) {
            if (!$this->evaluateRule($rule, $personaContext, $requestContext)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function evaluateRule(array $rule, array $personaContext, array $requestContext): bool
    {
        switch ($rule['type']) {
            case 'same_company':
                return ($personaContext['company_id'] ?? null) === 
                       ($requestContext['company_id'] ?? null);
                       
            case 'role_level':
                $userLevel = config("roles.levels.{$personaContext['role']}", 0);
                $requiredLevel = $rule['min_level'] ?? 0;
                return $userLevel >= $requiredLevel;
                
            case 'time_restriction':
                $allowedHours = $rule['allowed_hours'] ?? [];
                $currentHour = now()->hour;
                return in_array($currentHour, $allowedHours);
                
            case 'ip_restriction':
                $allowedIps = $rule['allowed_ips'] ?? [];
                $userIp = request()->ip();
                return in_array($userIp, $allowedIps);
                
            default:
                return true;
        }
    }
}

// Usage in controller
class DocumentController extends Controller
{
    public function show(Document $document)
    {
        $permissionService = app(ContextualPermissionService::class);
        
        $canView = $permissionService->checkPermission('view_document', [
            'document_id' => $document->id,
            'company_id' => $document->company_id,
            'sensitivity_level' => $document->sensitivity_level,
        ]);
        
        if (!$canView) {
            abort(403, 'Insufficient permissions for this document');
        }
        
        return view('documents.show', compact('document'));
    }
}
```

## Pattern 6: Persona Workflows

### Approval-Based Persona Changes

```php
class PersonaWorkflowService
{
    public function requestPersonaChange(
        User $user,
        string $targetRole,
        array $justification = []
    ): PersonaChangeRequest {
        $request = PersonaChangeRequest::create([
            'user_id' => $user->id,
            'requested_role' => $targetRole,
            'current_persona_id' => $user->activePersona()?->getId(),
            'justification' => $justification,
            'status' => 'pending',
            'requested_by' => auth()->id(),
        ]);
        
        // Notify approvers
        $this->notifyApprovers($request);
        
        return $request;
    }
    
    public function approvePersonaChange(PersonaChangeRequest $request, User $approver): void
    {
        $request->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
        
        // Create the new persona
        $user = $request->user;
        $persona = $user->createPersona([
            'name' => ucfirst($request->requested_role),
            'context' => [
                'role' => $request->requested_role,
                'permissions' => config("permissions.roles.{$request->requested_role}", []),
                'approved_by' => $approver->id,
                'approval_date' => now()->toISOString(),
            ]
        ]);
        
        // Automatically switch to new persona
        $user->switchToPersona($persona);
        
        // Notify user
        $user->notify(new PersonaChangeApproved($request, $persona));
    }
}
```

## Pattern 7: Persona Analytics

### Usage Tracking and Analytics

```php
class PersonaAnalyticsService
{
    public function trackPersonaUsage(PersonaInterface $persona, string $action, array $metadata = []): void
    {
        PersonaUsageLog::create([
            'persona_id' => $persona->getId(),
            'user_id' => $persona->getUser()->getKey(),
            'action' => $action,
            'metadata' => $metadata,
            'occurred_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
    
    public function getPersonaUsageStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'total_switches' => PersonaUsageLog::where('action', 'switched')
                ->where('occurred_at', '>=', $startDate)
                ->count(),
                
            'most_used_personas' => PersonaUsageLog::select('persona_id', DB::raw('count(*) as usage_count'))
                ->where('occurred_at', '>=', $startDate)
                ->groupBy('persona_id')
                ->orderByDesc('usage_count')
                ->limit(10)
                ->get(),
                
            'role_distribution' => $this->getRoleDistribution($startDate),
            
            'hourly_usage' => $this->getHourlyUsagePattern($startDate),
        ];
    }
    
    private function getRoleDistribution(Carbon $startDate): array
    {
        return PersonaUsageLog::join('personas', 'persona_usage_logs.persona_id', '=', 'personas.id')
            ->where('persona_usage_logs.occurred_at', '>=', $startDate)
            ->select(DB::raw("JSON_EXTRACT(personas.context, '$.role') as role"), DB::raw('count(*) as count'))
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();
    }
}
```

## Best Practices Summary

1. **Separation of Concerns**: Keep business logic separate from persona management
2. **Security First**: Always validate permissions and context
3. **Audit Everything**: Log important persona-related actions
4. **Performance**: Cache frequently accessed data
5. **Flexibility**: Design for extensibility and customization
6. **User Experience**: Provide clear feedback and intuitive flows
7. **Testing**: Write comprehensive tests for complex scenarios

## Next Steps

- [Performance Optimization](performance.md)
- [Security Best Practices](security.md)
- [Testing Strategies](testing.md)
