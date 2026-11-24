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

### 4. Gate Integration

By default, Laravel's Gate (`$user->can()`, `Gate::allows()`, `@can`, `$this->authorize()`, the `can` middleware) does not know about personas. Enable the integration in `config/multipersona.php` to evaluate abilities in the context of the active persona:

```php
'gate' => [
    'enabled' => true,
    'deny_unlisted' => false,
],
```

A `Gate::before` callback (`Grazulex\LaravelMultiPersona\Authorization\PersonaGate`) is then registered:

- an ability listed in the `permissions` of the user's active persona (checked through `canAccess()`) is **granted**;
- any other ability is **denied** when `deny_unlisted` is `true`; otherwise the decision is left to your regular gates and policies;
- users without an active persona, guests, and personas without a `permissions` list are not affected.

```php
$admin = $user->createPersona('Admin', ['permissions' => ['manage_users']]);
$client = $user->createPersona('Client', ['permissions' => ['view_dashboard']]);

$user->switchToPersona($admin);
$user->can('manage_users');   // true
$user->can('view_dashboard'); // false (or your own gate's answer)

$user->switchToPersona($client);
$user->can('manage_users');   // false (or your own gate's answer)
```

`Gate::forUser($other)` uses the active persona of `$other`. Because the callback runs before policies, a listed ability is granted whatever the arguments (`$user->can('update', $post)` is `true` when `update` is listed): list only abilities that need no per-model check, or keep those in policies. Overriding `canAccess()` in a custom persona model changes what the Gate grants.

### 5. Data Filtering per Persona

Add the `BelongsToPersona` trait to the models whose rows belong to a persona. It registers a global scope (`Grazulex\LaravelMultiPersona\Scopes\PersonaScope`) that restricts every Eloquent query on the model to the rows of the active persona.

```php
use Grazulex\LaravelMultiPersona\Traits\BelongsToPersona;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use BelongsToPersona;
}
```

The table needs a column referencing the persona, `persona_id` by default:

```php
Schema::table('documents', function (Blueprint $table) {
    $table->foreignId('persona_id')->nullable()->index();
});
```

```php
$user->switchToPersona($admin);

Document::all();                           // only the documents of $admin
Document::where('title', 'Report')->get(); // still filtered
Document::create(['title' => 'Report']);   // persona_id is set to $admin->id
$document->persona;                        // the owning Persona

$user->switchToPersona($client);

Document::all();                           // only the documents of $client
```

The scope applies to reads, counts, updates and deletes made through Eloquent. It does not apply to the query builder (`DB::table('documents')`). When a record is created, the persona column is filled from the active persona unless you set it yourself.

#### Configuration

```php
// config/multipersona.php
'scope' => [
    'column' => 'persona_id',
    'context_key' => null,
    'strict' => false,
],
```

- `column`: column of your models that references the persona.
- `context_key`: by default (`null`) the column is compared with the persona ID. Set a key of the persona context (dot notation is supported) to compare the column with that value instead, for instance `'company_id'` so that several personas of the same company share the same rows. An active persona that has no value for this key sees no row.
- `strict`: behaviour when **no persona is active** (guests, console commands, queued jobs, users who have not selected a persona). With `false` the queries are not filtered; with `true` they return no row.

> **Important**: with `strict` set to `false`, a request without active persona sees the rows of every persona. Enable `strict`, or protect the routes with the `EnsureActivePersona` middleware, when the filter is used to isolate data.

#### Per-model column

Override the trait methods to use another column or context key for one model:

```php
class Invoice extends Model
{
    use BelongsToPersona;

    public function getPersonaColumn(): string
    {
        return 'company_id';
    }

    public function getPersonaContextKey(): ?string
    {
        return 'company_id';
    }
}

$user->createPersona('Acme Corp Admin', ['company_id' => 1, 'role' => 'admin']);
```

#### Bypassing the filter

```php
Document::withoutPersonaScope()->get();   // every row
Document::forPersona($otherPersona)->get(); // rows of another persona
Document::forPersona(42)->get();          // rows whose column equals 42
```

`withoutGlobalScope(PersonaScope::class)` works as well. The `persona()` relationship of the trait is only meaningful when the column holds the persona ID (no `context_key`).

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

// Recorded persona switches (requires 'audit' => ['enabled' => true],
// see the Events Guide)
$switches = $user->personaSwitchLogs()->latest('switched_at')->get();
```

## Next Steps

- [Events and Listeners Guide](events-guide.md)
- [Advanced Middleware](middleware-guide.md)
- [Advanced Pattern Examples](advanced-patterns.md)
- [Frontend Framework Integration](frontend-integration.md)
