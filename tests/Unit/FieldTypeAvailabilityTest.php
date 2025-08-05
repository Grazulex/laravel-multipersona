<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;

it('shows all available field types in test environment', function () {
    $availableTypes = FieldTypeRegistry::all();

    // Debug output
    // echo "\n=== Available Field Types (ModelSchema) ===\n";
    foreach ($availableTypes as $type) {
        // echo "✓ {$type}\n";
    }
    // echo 'Total: '.count($availableTypes)." types\n";

    // Test specific types mentioned in the bug report
    $expectedTypes = [
        'string', 'text', 'longText', 'mediumText',
        'integer', 'bigInteger', 'unsignedBigInteger',
        'tinyInteger', 'smallInteger', 'mediumInteger',
        'boolean', 'decimal', 'float', 'double',
        'date', 'datetime', 'timestamp', 'time',
        'json', 'uuid', 'email',
        'foreignId', 'morphs', 'binary',
    ];

    // echo "\n=== Type Validation (ModelSchema FieldTypeRegistry) ===\n";
    foreach ($expectedTypes as $type) {
        $available = FieldTypeRegistry::has($type);
        // echo ($available ? '✅' : '❌')." {$type}\n";
        expect($available)->toBeTrue("Type '{$type}' should be available in ModelSchema");
    }

    expect(count($availableTypes))->toBeGreaterThanOrEqual(60, 'ModelSchema should have at least 60 field types (including aliases)');
});
