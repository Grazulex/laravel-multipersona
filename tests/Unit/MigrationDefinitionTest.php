<?php

declare(strict_types=1);

use Grazulex\LaravelTurbomaker\Schema\Field;

it('tests migration definition for all field types', function () {
    $typesToTest = [
        'string', 'text', 'longText', 'mediumText',
        'integer', 'bigInteger', 'unsignedBigInteger',
        'tinyInteger', 'smallInteger', 'mediumInteger',
        'boolean', 'decimal', 'float', 'double',
        'date', 'datetime', 'timestamp', 'time',
        'json', 'uuid', 'email', 'url',
        'foreignId', 'morphs', 'binary',
    ];

    // echo "\n=== Testing Migration Definitions ===\n";

    foreach ($typesToTest as $type) {
        // echo "Testing {$type}... ";

        try {
            // Créer un Field avec ce type
            $field = new Field(
                name: 'test_field',
                type: $type,
                nullable: false
            );

            // Obtenir la définition de migration
            $migrationDef = $field->getMigrationDefinition();
            $modifiers = $field->getMigrationModifiers();

            // echo "✅ {$migrationDef}";
            if (! empty($modifiers)) {
                // echo ' (modifiers: '.implode(', ', $modifiers).')';
            }
            // echo "\n";

            // Vérifier que la définition n'est pas vide
            expect($migrationDef)->not->toBeEmpty("Migration definition for {$type} should not be empty");

        } catch (Exception $e) {
            // echo '❌ ERROR: '.$e->getMessage()."\n";
            throw $e;
        }
    }

    // echo "========================\n";
});
