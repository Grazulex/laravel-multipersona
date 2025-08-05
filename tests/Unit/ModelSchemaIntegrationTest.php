<?php

declare(strict_types=1);

namespace Tests\Unit;

use Grazulex\LaravelModelschema\Services\SchemaService;

describe('ModelSchema Integration', function () {
    test('it can resolve schema service', function () {
        $service = app(SchemaService::class);

        expect($service)->toBeInstanceOf(SchemaService::class);
    });

    test('it has modelschema service provider class available', function () {
        expect(class_exists('Grazulex\\LaravelModelschema\\LaravelModelschemaServiceProvider'))->toBeTrue();
    });

    test('it can access field type registry', function () {
        $registryClass = \Grazulex\LaravelModelschema\Support\FieldTypeRegistry::class;

        expect(class_exists($registryClass))->toBeTrue();

        // Test that we can use the registry directly
        expect($registryClass::has('string'))->toBeTrue();
        expect($registryClass::has('integer'))->toBeTrue();
        expect($registryClass::has('boolean'))->toBeTrue();
        expect($registryClass::has('invalid_type'))->toBeFalse();

        // Test that we have extensive field types (30+)
        $allTypes = $registryClass::all();
        expect(count($allTypes))->toBeGreaterThanOrEqual(30);

        // Test specific advanced types that ModelSchema provides
        expect($registryClass::has('enum'))->toBeTrue();
        expect($registryClass::has('set'))->toBeTrue();
        expect($registryClass::has('geometry'))->toBeTrue();
        expect($registryClass::has('point'))->toBeTrue();
        expect($registryClass::has('polygon'))->toBeTrue();
    });
})->group('migration', 'integration');
