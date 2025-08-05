# Usage Guide - Laravel MultiPersona

## Introduction

Laravel MultiPersona allows your users to switch between different "roles" or "contexts" within your application. For example, a user can be both a client and an administrator of another company.

## Basic Usage

### 1. Creating Personas

```php
use App\Models\User;

$user = User::find(1);

// Create an administrator persona
$adminPersona = $user->createPersona([
    'name' => 'IT Administrator',
    'context' => [
        'role' => 'admin',
        'department' => 'IT',
        'permissions' => ['read', 'write', 'delete', 'manage_users'],
        'company_id' => 123,
    ]
]);

// Create a standard user persona
$userPersona = $user->createPersona([
    'name' => 'Standard User',
    'context' => [
        'role' => 'user',
        'department' => 'Sales',
        'permissions' => ['read', 'write'],
        'company_id' => 456,
    ]
]);
```

### 2. Switching Between Personas

```php
// Activate a persona
$user->switchToPersona($adminPersona);

// Check active persona
if ($user->hasActivePersona()) {
    $current = $user->activePersona();
    echo "Active persona: " . $current->name;
}

// Use global helpers
$currentPersona = persona(); // Currently active persona
$allPersonas = personas($user); // All user's personas
```

### 3. Managing Permissions

```php
// Check permissions
if (persona() && persona()->canAccess('admin_panel')) {
    // User can access admin panel
}

// Access context
$context = persona()->getContext();
$role = $context['role'] ?? 'guest';
$companyId = $context['company_id'] ?? null;

// Check role
if ($role === 'admin') {
    // Administrator actions
}
```

## Advanced Usage Examples

### 1. Multi-tenant System

```php
class TenantController extends Controller
{
    public function switchTenant($tenantId)
    {
        $user = auth()->user();
        
        // Find persona for this tenant
        $tenantPersona = $user->personas()
            ->whereJsonContains('context->tenant_id', $tenantId)
            ->first();
            
        if ($tenantPersona) {
            $user->switchToPersona($tenantPersona);
            
            return redirect()->route('dashboard')
                ->with('success', "Switched to {$tenantPersona->name}");
        }
        
        return back()->with('error', 'Unauthorized access to this tenant');
    }
    
    public function getCurrentTenant()
    {
        $persona = persona();
        
        if ($persona) {
            $context = $persona->getContext();
            return Tenant::find($context['tenant_id'] ?? null);
        }
        
        return null;
    }
}
```

### 2. Role-based Protection Middleware

```php
class RequireAdminPersona
{
    public function handle(Request $request, Closure $next)
    {
        $persona = persona();
        
        if (!$persona) {
            return redirect()->route('select-persona')
                ->with('error', 'Please select a persona');
        }
        
        $context = $persona->getContext();
        if (($context['role'] ?? '') !== 'admin') {
            abort(403, 'Access restricted to administrators');
        }
        
        return $next($request);
    }
}
```

### 3. Blade Component for Persona Selection

```blade
{{-- resources/views/components/persona-selector.blade.php --}}
@if(auth()->check())
    @php
        $currentPersona = persona();
        $userPersonas = personas(auth()->user());
    @endphp
    
    <div class="persona-selector">
        <button class="dropdown-toggle" type="button" data-toggle="dropdown">
            @if($currentPersona)
                <i class="icon-user"></i>
                {{ $currentPersona->getName() }}
                <small class="text-muted">
                    {{ $currentPersona->getContext()['role'] ?? 'No role' }}
                </small>
            @else
                <i class="icon-users"></i>
                Select a profile
            @endif
            <i class="icon-chevron-down"></i>
        </button>
        
        <div class="dropdown-menu">
            @forelse($userPersonas as $persona)
                <a href="{{ route('persona.switch', $persona->getId()) }}" 
                   class="dropdown-item {{ $currentPersona && $currentPersona->getId() === $persona->getId() ? 'active' : '' }}">
                    <strong>{{ $persona->getName() }}</strong>
                    <br>
                    <small class="text-muted">
                        {{ $persona->getContext()['role'] ?? 'No role' }}
                        @if(isset($persona->getContext()['company_name']))
                            - {{ $persona->getContext()['company_name'] }}
                        @endif
                    </small>
                </a>
            @empty
                <span class="dropdown-item text-muted">No personas available</span>
            @endforelse
            
            @if($currentPersona)
                <div class="dropdown-divider"></div>
                <a href="{{ route('persona.clear') }}" class="dropdown-item text-danger">
                    <i class="icon-logout"></i>
                    Disable profile
                </a>
            @endif
        </div>
    </div>
@endif
```

### 4. REST API for Frontend Applications

```php
class PersonaApiController extends Controller
{
    /**
     * List personas for authenticated user
     */
    public function index()
    {
        $user = auth()->user();
        $personas = personas($user);
        
        return response()->json([
            'current_persona' => persona() ? [
                'id' => persona()->getId(),
                'name' => persona()->getName(),
                'context' => persona()->getContext(),
                'is_active' => persona()->isActive(),
            ] : null,
            'available_personas' => $personas->map(function ($persona) {
                return [
                    'id' => $persona->getId(),
                    'name' => $persona->getName(),
                    'context' => $persona->getContext(),
                    'is_active' => $persona->isActive(),
                ];
            }),
        ]);
    }
    
    /**
     * Switch to a persona
     */
    public function switch(Request $request)
    {
        $request->validate([
            'persona_id' => 'required|integer|exists:personas,id',
        ]);
        
        $user = auth()->user();
        $persona = $user->personas()->find($request->persona_id);
        
        if (!$persona) {
            return response()->json([
                'error' => 'Persona not found or unauthorized access'
            ], 403);
        }
        
        $user->switchToPersona($persona);
        
        return response()->json([
            'message' => 'Persona activated successfully',
            'current_persona' => [
                'id' => $persona->getId(),
                'name' => $persona->getName(),
                'context' => $persona->getContext(),
            ],
        ]);
    }
    
    /**
     * Clear current persona
     */
    public function clear()
    {
        app('multipersona')->clear();
        
        return response()->json([
            'message' => 'Persona deactivated successfully'
        ]);
    }
}
```

### 5. Integration with Vue.js/React

```javascript
// PersonaService.js
class PersonaService {
    async getPersonas() {
        const response = await fetch('/api/personas', {
            headers: {
                'Authorization': `Bearer ${this.getToken()}`,
                'Accept': 'application/json',
            }
        });
        return response.json();
    }
    
    async switchPersona(personaId) {
        const response = await fetch('/api/personas/switch', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.getToken()}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ persona_id: personaId })
        });
        return response.json();
    }
    
    async clearPersona() {
        const response = await fetch('/api/personas/clear', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.getToken()}`,
                'Accept': 'application/json',
            }
        });
        return response.json();
    }
    
    getToken() {
        return localStorage.getItem('auth_token');
    }
}

// Vue.js Component
export default {
    data() {
        return {
            currentPersona: null,
            availablePersonas: [],
            personaService: new PersonaService()
        }
    },
    
    async mounted() {
        await this.loadPersonas();
    },
    
    methods: {
        async loadPersonas() {
            try {
                const data = await this.personaService.getPersonas();
                this.currentPersona = data.current_persona;
                this.availablePersonas = data.available_personas;
            } catch (error) {
                console.error('Error loading personas:', error);
            }
        },
        
        async switchTo(personaId) {
            try {
                await this.personaService.switchPersona(personaId);
                await this.loadPersonas();
                
                // Reload page or emit event
                this.$emit('persona-changed');
                
            } catch (error) {
                console.error('Error switching persona:', error);
            }
        }
    }
}
```

## Available Helpers

### Global Helpers

```php
// Get active persona
$currentPersona = persona();

// Get all personas for a user
$userPersonas = personas($user);

// Check if a persona is active
if (persona()) {
    // A persona is active
}
```

### HasPersonas Trait Methods

```php
$user = auth()->user();

// Create a new persona
$persona = $user->createPersona($data);

// Get all personas
$personas = $user->personas;

// Get active persona
$active = $user->activePersona();

// Check if user has an active persona
if ($user->hasActivePersona()) {
    // ...
}

// Switch to a persona
$user->switchToPersona($persona);

// Get persona by name
$persona = $user->getPersonaByName('Admin');

// Check if user has a specific persona
if ($user->hasPersona('Admin')) {
    // ...
}
```

## Next Steps

- [Events and Listeners Guide](events-guide.md)
- [Advanced Middleware](middleware-guide.md)
- [Advanced Pattern Examples](advanced-patterns.md)
- [Frontend Framework Integration](frontend-integration.md)
