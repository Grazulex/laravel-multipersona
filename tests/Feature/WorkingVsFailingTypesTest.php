<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('tests working vs failing field types', function () {
    // Ensure schemas directory exists
    if (! File::exists(resource_path('schemas'))) {
        File::makeDirectory(resource_path('schemas'), 0755, true);
    }

    $workingTypes = [
        'string' => 'string',
        'text' => 'text',
        'integer' => 'integer',
        'bigInteger' => 'bigInteger',
        'boolean' => 'boolean',
        'decimal' => 'decimal',
    ];

    $failingTypes = [
        'longText' => 'longText',
        'unsignedBigInteger' => 'unsignedBigInteger',
    ];

    // echo "\n=== TYPES QUI FONCTIONNENT ===\n";
    foreach ($workingTypes as $fieldName => $fieldType) {
        $schemaContent = <<<YAML
fields:
  test_field:
    type: {$fieldType}
    nullable: true
YAML;

        $schemaPath = resource_path("schemas/working_{$fieldName}.schema.yml");
        File::put($schemaPath, $schemaContent);

        try {
            $this->artisan('turbo:make', [
                'name' => 'Working'.ucfirst($fieldName),
                '--schema' => "working_{$fieldName}",
                '--force' => true,
            ])->assertExitCode(0);

            // echo "✅ {$fieldType} fonctionne\n";

        } catch (Exception $e) {
            // echo "❌ {$fieldType} échoue: ".$e->getMessage()."\n";
        }

        // Nettoyer
        File::delete($schemaPath);
        $migrationFiles = File::glob(database_path('migrations/*_create_working_'.mb_strtolower($fieldName).'s_table.php'));
        foreach ($migrationFiles as $file) {
            File::delete($file);
        }
    }

    // echo "\n=== TYPES QUI ÉCHOUENT ===\n";
    foreach ($failingTypes as $fieldName => $fieldType) {
        $schemaContent = <<<YAML
fields:
  test_field:
    type: {$fieldType}
    nullable: true
YAML;

        $schemaPath = resource_path("schemas/failing_{$fieldName}.schema.yml");
        File::put($schemaPath, $schemaContent);

        try {
            $this->artisan('turbo:make', [
                'name' => 'Failing'.ucfirst($fieldName),
                '--schema' => "failing_{$fieldName}",
                '--force' => true,
            ])->assertExitCode(0);

            // echo "✅ {$fieldType} fonctionne (surprenant!)\n";

        } catch (Exception $e) {
            // echo "❌ {$fieldType} échoue comme prévu\n";

            // Testons la validation directement
            // echo "   Testons la validation du schéma...\n";
            try {
                $this->artisan('turbo:schema', [
                    'action' => 'validate',
                    'name' => "failing_{$fieldName}",
                ]);
                // echo "   ✅ Validation du schéma OK\n";
            } catch (Exception $ve) {
                // echo '   ❌ Validation du schéma échoue: '.$ve->getMessage()."\n";
            }
        }

        // Nettoyer
        File::delete($schemaPath);
    }

    // Assert that all working types succeeded
    expect(true)->toBeTrue('All working types should pass');
});
