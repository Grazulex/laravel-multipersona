<?php

use App\Models\User;
use Grazulex\MultiPersona\Contracts\PersonaInterface;
use Grazulex\MultiPersona\PersonaManager;

$user = User::find(1);

/** @var PersonaManager $manager */
$manager = app(PersonaManager::class);

// List personas
$personas = $manager->getAllForUser($user);

// Set active persona
$manager->setActive($personas->first());

// Use helper
$current = persona(); // returns PersonaInterface
