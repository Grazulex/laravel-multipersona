<?php

declare(strict_types=1);

use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->personaManager = app(PersonaManager::class);

    // Create personas table manually
    Schema::create('personas', function ($table) {
        $table->id();
        $table->string('name');
        $table->json('context')->nullable();
        $table->boolean('is_active')->default(false);
        $table->unsignedBigInteger('user_id');
        $table->string('user_type')->default('App\Models\User');
        $table->timestamps();

        $table->index(['user_id', 'user_type']);
        $table->index(['user_id', 'is_active']);
    });
});

it('can create a persona manager instance', function () {
    expect($this->personaManager)->toBeInstanceOf(PersonaManager::class);
});

it('returns null when no active persona is set', function () {
    expect($this->personaManager->current())->toBeNull();
});

it('can set and get current persona', function () {
    $persona = Persona::factory()->create();

    $this->personaManager->setActive($persona);

    expect($this->personaManager->current())->not->toBeNull();
    expect($this->personaManager->id())->toBe($persona->id);
});

it('can clear active persona', function () {
    $persona = Persona::factory()->create();

    $this->personaManager->setActive($persona);
    expect($this->personaManager->hasActive())->toBeTrue();

    $this->personaManager->clear();
    expect($this->personaManager->hasActive())->toBeFalse();
});

it('can get persona context', function () {
    $context = ['role' => 'admin', 'permissions' => ['read', 'write']];
    $persona = Persona::factory()->create(['context' => $context]);

    $this->personaManager->setActive($persona);

    expect($this->personaManager->context())->toBe($context);
});

it('returns empty array when no context is available', function () {
    expect($this->personaManager->context())->toBe([]);
});
