<?php

declare(strict_types=1);

use Grazulex\LaravelMultiPersona\Services\PersonaManager;

it('can resolve persona manager from container', function () {
    $manager = app(PersonaManager::class);

    expect($manager)->toBeInstanceOf(PersonaManager::class);
});

it('can resolve multipersona service from container', function () {
    $service = app('multipersona');

    expect($service)->toBeInstanceOf(PersonaManager::class);
});

it('has multipersona configuration available', function () {
    expect(config('multipersona'))->toBeArray();
    expect(config('multipersona.user_model'))->toBe('App\Models\User');
});
