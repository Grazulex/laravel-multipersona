# Frontend Integration Guide - Laravel MultiPersona

## Introduction

This guide covers how to integrate Laravel MultiPersona with modern frontend frameworks and build rich user interfaces for persona management.

## Vue.js Integration

### 1. Composable for Persona Management

```javascript
// composables/usePersona.js
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

export function usePersona() {
    const currentPersona = ref(null)
    const availablePersonas = ref([])
    const isLoading = ref(false)
    const error = ref(null)
    const router = useRouter()
    
    const hasActivePersona = computed(() => currentPersona.value !== null)
    const canSwitchPersonas = computed(() => availablePersonas.value.length > 1)
    
    const loadPersonas = async () => {
        isLoading.value = true
        error.value = null
        
        try {
            const response = await fetch('/api/personas', {
                headers: {
                    'Authorization': `Bearer ${getAuthToken()}`,
                    'Accept': 'application/json',
                }
            })
            
            if (!response.ok) {
                throw new Error('Failed to load personas')
            }
            
            const data = await response.json()
            currentPersona.value = data.current_persona
            availablePersonas.value = data.available_personas
            
        } catch (err) {
            error.value = err.message
            console.error('Error loading personas:', err)
        } finally {
            isLoading.value = false
        }
    }
    
    const switchPersona = async (personaId) => {
        isLoading.value = true
        error.value = null
        
        try {
            const response = await fetch('/api/personas/switch', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getAuthToken()}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ persona_id: personaId })
            })
            
            if (!response.ok) {
                throw new Error('Failed to switch persona')
            }
            
            const data = await response.json()
            currentPersona.value = data.current_persona
            
            // Emit event for other components
            window.dispatchEvent(new CustomEvent('persona-changed', {
                detail: { persona: data.current_persona }
            }))
            
            // Optionally reload page or redirect
            if (router.currentRoute.value.meta.requiresReload) {
                window.location.reload()
            }
            
        } catch (err) {
            error.value = err.message
            console.error('Error switching persona:', err)
        } finally {
            isLoading.value = false
        }
    }
    
    const clearPersona = async () => {
        isLoading.value = true
        error.value = null
        
        try {
            const response = await fetch('/api/personas/clear', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getAuthToken()}`,
                    'Accept': 'application/json',
                }
            })
            
            if (!response.ok) {
                throw new Error('Failed to clear persona')
            }
            
            currentPersona.value = null
            
            // Redirect to persona selection
            router.push('/select-persona')
            
        } catch (err) {
            error.value = err.message
            console.error('Error clearing persona:', err)
        } finally {
            isLoading.value = false
        }
    }
    
    const hasPermission = (permission) => {
        if (!currentPersona.value) return false
        
        const permissions = currentPersona.value.context?.permissions || []
        return permissions.includes(permission)
    }
    
    const hasRole = (role) => {
        if (!currentPersona.value) return false
        
        return currentPersona.value.context?.role === role
    }
    
    const getAuthToken = () => {
        return localStorage.getItem('auth_token') || 
               document.querySelector('meta[name="csrf-token"]')?.content
    }
    
    return {
        currentPersona,
        availablePersonas,
        isLoading,
        error,
        hasActivePersona,
        canSwitchPersonas,
        loadPersonas,
        switchPersona,
        clearPersona,
        hasPermission,
        hasRole
    }
}
```

### 2. Persona Selector Component

```vue
<!-- components/PersonaSelector.vue -->
<template>
    <div class="persona-selector">
        <div v-if="isLoading" class="spinner">Loading...</div>
        
        <div v-else-if="error" class="error">
            {{ error }}
            <button @click="loadPersonas" class="retry-btn">Retry</button>
        </div>
        
        <div v-else class="persona-dropdown">
            <button 
                @click="toggleDropdown" 
                class="persona-button"
                :class="{ 'active': isOpen }"
            >
                <div v-if="hasActivePersona" class="current-persona">
                    <div class="persona-name">{{ currentPersona.name }}</div>
                    <div class="persona-role">{{ currentPersona.context?.role || 'No role' }}</div>
                </div>
                <div v-else class="no-persona">
                    Select a persona
                </div>
                <ChevronDownIcon class="chevron" />
            </button>
            
            <div v-if="isOpen" class="dropdown-menu">
                <div 
                    v-for="persona in availablePersonas" 
                    :key="persona.id"
                    @click="handlePersonaClick(persona.id)"
                    class="persona-option"
                    :class="{ 'active': persona.id === currentPersona?.id }"
                >
                    <div class="persona-info">
                        <div class="name">{{ persona.name }}</div>
                        <div class="details">
                            {{ persona.context?.role || 'No role' }}
                            <span v-if="persona.context?.company_name" class="company">
                                @ {{ persona.context.company_name }}
                            </span>
                        </div>
                    </div>
                    <CheckIcon v-if="persona.id === currentPersona?.id" class="check-icon" />
                </div>
                
                <div v-if="hasActivePersona" class="dropdown-divider"></div>
                
                <button 
                    v-if="hasActivePersona"
                    @click="handleClearPersona"
                    class="clear-persona-btn"
                >
                    <LogoutIcon class="icon" />
                    Clear persona
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { usePersona } from '@/composables/usePersona'
import { ChevronDownIcon, CheckIcon, LogoutIcon } from '@heroicons/vue/24/outline'

const { 
    currentPersona, 
    availablePersonas, 
    isLoading, 
    error, 
    hasActivePersona,
    loadPersonas, 
    switchPersona, 
    clearPersona 
} = usePersona()

const isOpen = ref(false)

const toggleDropdown = () => {
    isOpen.value = !isOpen.value
}

const handlePersonaClick = async (personaId) => {
    if (personaId !== currentPersona.value?.id) {
        await switchPersona(personaId)
    }
    isOpen.value = false
}

const handleClearPersona = async () => {
    await clearPersona()
    isOpen.value = false
}

const closeDropdown = (event) => {
    if (!event.target.closest('.persona-selector')) {
        isOpen.value = false
    }
}

onMounted(() => {
    loadPersonas()
    document.addEventListener('click', closeDropdown)
})

onUnmounted(() => {
    document.removeEventListener('click', closeDropdown)
})
</script>

<style scoped>
.persona-selector {
    position: relative;
    display: inline-block;
}

.persona-button {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.persona-button:hover {
    border-color: #9ca3af;
}

.current-persona {
    text-align: left;
}

.persona-name {
    font-weight: 600;
    color: #111827;
}

.persona-role {
    font-size: 0.875rem;
    color: #6b7280;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    z-index: 50;
    margin-top: 4px;
}

.persona-option {
    display: flex;
    align-items: center;
    padding: 12px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
}

.persona-option:hover {
    background: #f9fafb;
}

.persona-option.active {
    background: #eff6ff;
}

.persona-info {
    flex: 1;
}

.persona-info .name {
    font-weight: 600;
    color: #111827;
}

.persona-info .details {
    font-size: 0.875rem;
    color: #6b7280;
}

.check-icon {
    width: 20px;
    height: 20px;
    color: #3b82f6;
}

.clear-persona-btn {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 12px;
    background: none;
    border: none;
    color: #dc2626;
    cursor: pointer;
    font-size: 0.875rem;
}

.clear-persona-btn:hover {
    background: #fef2f2;
}

.clear-persona-btn .icon {
    width: 16px;
    height: 16px;
    margin-right: 8px;
}
</style>
```

### 3. Permission Directive

```javascript
// directives/permission.js
export default {
    mounted(el, binding) {
        const { value: permission, modifiers } = binding
        
        if (!checkPermission(permission)) {
            if (modifiers.hide) {
                el.style.display = 'none'
            } else {
                el.disabled = true
                el.classList.add('permission-disabled')
            }
        }
    },
    
    updated(el, binding) {
        const { value: permission, modifiers } = binding
        
        if (!checkPermission(permission)) {
            if (modifiers.hide) {
                el.style.display = 'none'
            } else {
                el.disabled = true
                el.classList.add('permission-disabled')
            }
        } else {
            el.style.display = ''
            el.disabled = false
            el.classList.remove('permission-disabled')
        }
    }
}

function checkPermission(permission) {
    // Get current persona from global state or context
    const currentPersona = window.currentPersona || JSON.parse(
        document.querySelector('meta[name="current-persona"]')?.content || 'null'
    )
    
    if (!currentPersona) return false
    
    const permissions = currentPersona.context?.permissions || []
    return permissions.includes(permission)
}

// Usage in main.js
import { createApp } from 'vue'
import permissionDirective from './directives/permission'

const app = createApp(App)
app.directive('permission', permissionDirective)
```

## React Integration

### 1. Persona Context and Hook

```javascript
// contexts/PersonaContext.js
import React, { createContext, useContext, useReducer, useEffect } from 'react'

const PersonaContext = createContext()

const personaReducer = (state, action) => {
    switch (action.type) {
        case 'SET_LOADING':
            return { ...state, isLoading: action.payload }
        case 'SET_ERROR':
            return { ...state, error: action.payload, isLoading: false }
        case 'SET_PERSONAS':
            return {
                ...state,
                currentPersona: action.payload.current,
                availablePersonas: action.payload.available,
                isLoading: false,
                error: null
            }
        case 'SET_CURRENT_PERSONA':
            return { ...state, currentPersona: action.payload }
        case 'CLEAR_PERSONA':
            return { ...state, currentPersona: null }
        default:
            return state
    }
}

export const PersonaProvider = ({ children }) => {
    const [state, dispatch] = useReducer(personaReducer, {
        currentPersona: null,
        availablePersonas: [],
        isLoading: false,
        error: null
    })
    
    const loadPersonas = async () => {
        dispatch({ type: 'SET_LOADING', payload: true })
        
        try {
            const response = await fetch('/api/personas', {
                headers: {
                    'Authorization': `Bearer ${getAuthToken()}`,
                    'Accept': 'application/json',
                }
            })
            
            if (!response.ok) {
                throw new Error('Failed to load personas')
            }
            
            const data = await response.json()
            dispatch({
                type: 'SET_PERSONAS',
                payload: {
                    current: data.current_persona,
                    available: data.available_personas
                }
            })
        } catch (error) {
            dispatch({ type: 'SET_ERROR', payload: error.message })
        }
    }
    
    const switchPersona = async (personaId) => {
        dispatch({ type: 'SET_LOADING', payload: true })
        
        try {
            const response = await fetch('/api/personas/switch', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getAuthToken()}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ persona_id: personaId })
            })
            
            if (!response.ok) {
                throw new Error('Failed to switch persona')
            }
            
            const data = await response.json()
            dispatch({ type: 'SET_CURRENT_PERSONA', payload: data.current_persona })
            
            // Emit custom event
            window.dispatchEvent(new CustomEvent('persona-changed', {
                detail: { persona: data.current_persona }
            }))
            
        } catch (error) {
            dispatch({ type: 'SET_ERROR', payload: error.message })
        }
    }
    
    const clearPersona = async () => {
        try {
            await fetch('/api/personas/clear', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getAuthToken()}`,
                    'Accept': 'application/json',
                }
            })
            
            dispatch({ type: 'CLEAR_PERSONA' })
        } catch (error) {
            dispatch({ type: 'SET_ERROR', payload: error.message })
        }
    }
    
    const hasPermission = (permission) => {
        const permissions = state.currentPersona?.context?.permissions || []
        return permissions.includes(permission)
    }
    
    const hasRole = (role) => {
        return state.currentPersona?.context?.role === role
    }
    
    const getAuthToken = () => {
        return localStorage.getItem('auth_token') ||
               document.querySelector('meta[name="csrf-token"]')?.content
    }
    
    useEffect(() => {
        loadPersonas()
    }, [])
    
    const value = {
        ...state,
        loadPersonas,
        switchPersona,
        clearPersona,
        hasPermission,
        hasRole
    }
    
    return (
        <PersonaContext.Provider value={value}>
            {children}
        </PersonaContext.Provider>
    )
}

export const usePersona = () => {
    const context = useContext(PersonaContext)
    if (!context) {
        throw new Error('usePersona must be used within a PersonaProvider')
    }
    return context
}
```

### 2. Persona Selector Component

```javascript
// components/PersonaSelector.jsx
import React, { useState, useRef, useEffect } from 'react'
import { usePersona } from '../contexts/PersonaContext'
import { ChevronDownIcon, CheckIcon, LogoutIcon } from '@heroicons/react/24/outline'

const PersonaSelector = () => {
    const {
        currentPersona,
        availablePersonas,
        isLoading,
        error,
        switchPersona,
        clearPersona
    } = usePersona()
    
    const [isOpen, setIsOpen] = useState(false)
    const dropdownRef = useRef(null)
    
    const handlePersonaClick = async (personaId) => {
        if (personaId !== currentPersona?.id) {
            await switchPersona(personaId)
        }
        setIsOpen(false)
    }
    
    const handleClearPersona = async () => {
        await clearPersona()
        setIsOpen(false)
    }
    
    const handleClickOutside = (event) => {
        if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
            setIsOpen(false)
        }
    }
    
    useEffect(() => {
        document.addEventListener('mousedown', handleClickOutside)
        return () => document.removeEventListener('mousedown', handleClickOutside)
    }, [])
    
    if (isLoading) {
        return <div className="animate-spin">Loading...</div>
    }
    
    if (error) {
        return (
            <div className="text-red-600">
                {error}
                <button onClick={() => window.location.reload()} className="ml-2 underline">
                    Retry
                </button>
            </div>
        )
    }
    
    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg hover:border-gray-400 transition-colors"
            >
                {currentPersona ? (
                    <div className="text-left">
                        <div className="font-semibold text-gray-900">{currentPersona.name}</div>
                        <div className="text-sm text-gray-600">
                            {currentPersona.context?.role || 'No role'}
                        </div>
                    </div>
                ) : (
                    <div className="text-gray-600">Select a persona</div>
                )}
                <ChevronDownIcon className="ml-2 h-5 w-5 text-gray-400" />
            </button>
            
            {isOpen && (
                <div className="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg z-50">
                    {availablePersonas.map((persona) => (
                        <div
                            key={persona.id}
                            onClick={() => handlePersonaClick(persona.id)}
                            className={`flex items-center px-4 py-3 cursor-pointer hover:bg-gray-50 border-b border-gray-100 last:border-b-0 ${
                                persona.id === currentPersona?.id ? 'bg-blue-50' : ''
                            }`}
                        >
                            <div className="flex-1">
                                <div className="font-semibold text-gray-900">{persona.name}</div>
                                <div className="text-sm text-gray-600">
                                    {persona.context?.role || 'No role'}
                                    {persona.context?.company_name && (
                                        <span className="ml-1">@ {persona.context.company_name}</span>
                                    )}
                                </div>
                            </div>
                            {persona.id === currentPersona?.id && (
                                <CheckIcon className="h-5 w-5 text-blue-600" />
                            )}
                        </div>
                    ))}
                    
                    {currentPersona && (
                        <>
                            <div className="border-t border-gray-200"></div>
                            <button
                                onClick={handleClearPersona}
                                className="flex items-center w-full px-4 py-3 text-red-600 hover:bg-red-50 transition-colors"
                            >
                                <LogoutIcon className="h-4 w-4 mr-2" />
                                Clear persona
                            </button>
                        </>
                    )}
                </div>
            )}
        </div>
    )
}

export default PersonaSelector
```

### 3. Permission Component and Hook

```javascript
// components/PermissionGate.jsx
import { usePersona } from '../contexts/PersonaContext'

const PermissionGate = ({ 
    permission, 
    role, 
    fallback = null, 
    children 
}) => {
    const { hasPermission, hasRole } = usePersona()
    
    let allowed = true
    
    if (permission && !hasPermission(permission)) {
        allowed = false
    }
    
    if (role && !hasRole(role)) {
        allowed = false
    }
    
    if (!allowed) {
        return fallback
    }
    
    return children
}

export default PermissionGate

// Usage
import PermissionGate from './components/PermissionGate'

function AdminPanel() {
    return (
        <PermissionGate 
            permission="manage_users"
            fallback={<div>Access denied</div>}
        >
            <div>Admin content here</div>
        </PermissionGate>
    )
}
```

## Alpine.js Integration

### Simple Persona Selector

```html
<!-- Persona Selector with Alpine.js -->
<div x-data="personaSelector()" x-init="loadPersonas()" class="relative">
    <button 
        @click="isOpen = !isOpen"
        class="flex items-center px-4 py-2 bg-white border rounded-lg"
    >
        <div x-show="currentPersona">
            <div class="font-semibold" x-text="currentPersona?.name"></div>
            <div class="text-sm text-gray-600" x-text="currentPersona?.context?.role"></div>
        </div>
        <div x-show="!currentPersona" class="text-gray-600">Select persona</div>
        <svg class="ml-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
    
    <div 
        x-show="isOpen" 
        @click.outside="isOpen = false"
        x-transition
        class="absolute top-full left-0 right-0 mt-1 bg-white border rounded-lg shadow-lg z-50"
    >
        <template x-for="persona in availablePersonas" :key="persona.id">
            <div 
                @click="switchPersona(persona.id)"
                class="flex items-center px-4 py-3 cursor-pointer hover:bg-gray-50"
                :class="persona.id === currentPersona?.id ? 'bg-blue-50' : ''"
            >
                <div class="flex-1">
                    <div class="font-semibold" x-text="persona.name"></div>
                    <div class="text-sm text-gray-600" x-text="persona.context?.role"></div>
                </div>
                <svg 
                    x-show="persona.id === currentPersona?.id"
                    class="h-5 w-5 text-blue-600" 
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </template>
    </div>
</div>

<script>
function personaSelector() {
    return {
        currentPersona: null,
        availablePersonas: [],
        isOpen: false,
        isLoading: false,
        
        async loadPersonas() {
            this.isLoading = true
            try {
                const response = await fetch('/api/personas', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                
                const data = await response.json()
                this.currentPersona = data.current_persona
                this.availablePersonas = data.available_personas
            } catch (error) {
                console.error('Error loading personas:', error)
            } finally {
                this.isLoading = false
            }
        },
        
        async switchPersona(personaId) {
            if (personaId === this.currentPersona?.id) {
                this.isOpen = false
                return
            }
            
            try {
                const response = await fetch('/api/personas/switch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ persona_id: personaId })
                })
                
                const data = await response.json()
                this.currentPersona = data.current_persona
                this.isOpen = false
                
                // Optionally reload page
                window.location.reload()
            } catch (error) {
                console.error('Error switching persona:', error)
            }
        }
    }
}
</script>
```

## Best Practices

1. **State Management**: Use proper state management (Vuex/Pinia, Redux/Zustand) for complex apps
2. **Error Handling**: Always handle API errors gracefully
3. **Loading States**: Show loading indicators during persona operations
4. **Real-time Updates**: Consider WebSocket integration for real-time persona changes
5. **Accessibility**: Ensure keyboard navigation and screen reader support
6. **Performance**: Cache persona data appropriately
7. **Security**: Never trust client-side permission checks alone

## Next Steps

- [API Reference](api-reference.md)
- [Security Best Practices](security.md)
- [Performance Optimization](performance.md)
