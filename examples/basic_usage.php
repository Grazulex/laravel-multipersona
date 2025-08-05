<?php

declare(strict_types=1);

/**
 * Laravel MultiPersona - Basic Usage Example
 *
 * This example demonstrates how to use Laravel MultiPersona
 * to manage different user contexts (personas) in your application.
 */

use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Grazulex\LaravelMultiPersona\Traits\HasPersonas;
use Illuminate\Database\Eloquent\Model;

// Step 1: Add the HasPersonas trait to your User model
class User extends Model
{
    use HasPersonas;

    protected $fillable = ['name', 'email'];
}

// Step 2: Create personas for a user
$user = User::find(1);

// Create different personas for different contexts
$adminPersona = $user->createPersona('Company Admin', [
    'role' => 'admin',
    'company_id' => 123,
    'permissions' => ['read', 'write', 'delete', 'manage_users'],
]);

$userPersona = $user->createPersona('Regular User', [
    'role' => 'user',
    'company_id' => 123,
    'permissions' => ['read', 'write'],
]);

$clientPersona = $user->createPersona('Client View', [
    'role' => 'client',
    'company_id' => 456,
    'permissions' => ['read'],
]);

// Step 3: Switch between personas
$user->switchToPersona($adminPersona);

// Step 4: Use the PersonaManager service
/** @var PersonaManager $personaManager */
$personaManager = app(PersonaManager::class);

// List personas
$personas = $personaManager->forUser($user);

// Set active persona
$personaManager->setActive($personas->first());

// Use helper
// Get current active persona
$currentPersona = $personaManager->current();
echo 'Current persona: '.$currentPersona->getName();

// Get persona context
$context = $personaManager->context();
echo 'Current role: '.$context['role'];

// Check permissions
if (in_array('manage_users', $context['permissions'])) {
    echo 'User can manage other users';
}

// Step 5: Use helper functions
$currentPersonaId = persona()->getId();
$allUserPersonas = personas($user);

// Step 6: Switch programmatically
$personaManager->switchTo($clientPersona);

// Step 7: Clear active persona
$personaManager->clear();

echo 'Basic usage example completed!';
