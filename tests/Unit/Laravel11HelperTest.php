<?php

declare(strict_types=1);

use Grazulex\LaravelTurbomaker\Support\Laravel11Helper;

it('provides correct API route instructions structure', function () {
    $instructions = Laravel11Helper::getApiRouteInstructions();

    expect($instructions)->toBeArray();

    // Each instruction should have the required structure
    foreach ($instructions as $instruction) {
        expect($instruction)->toHaveKey('type');
        expect($instruction)->toHaveKey('message');
        expect($instruction)->toHaveKey('command');

        expect($instruction['type'])->toBeString();
        expect($instruction['message'])->toBeString();
        expect($instruction['command'])->toBeString();
    }
});

it('handles version detection logic', function () {
    // Test the basic logic without mocking Laravel app
    // This tests that the method exists and returns a boolean
    $result = Laravel11Helper::isLaravel11OrHigher();
    expect($result)->toBeBool();
});

it('tests api route enablement check', function () {
    // Test that the method exists and returns a boolean
    $result = Laravel11Helper::ensureApiRoutesEnabled();
    expect($result)->toBeBool();
});
