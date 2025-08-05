<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('generates improved tests with correct naming conventions and factory data', function () {
    // Generate a simple module with tests (no schema needed)
    $this->artisan('turbo:make TestConventions --tests --force')
        ->assertExitCode(0);

    // Check feature test content for improved structure
    $featureTestPath = base_path('tests/Feature/TestConventionsTest.php');
    if (file_exists($featureTestPath)) {
        $featureContent = file_get_contents($featureTestPath);

        // Test should use factory data instead of hardcoded values
        expect($featureContent)->toContain('::factory()->make()->toArray()');

        // Test should use correct table name
        expect($featureContent)->toContain('test_conventions');

        // Test should use correct route naming (plural kebab-case)
        expect($featureContent)->toContain('test-conventions');

        // Clean up
        unlink($featureTestPath);
    }

    // Check unit test content for correct fillable validation
    $unitTestPath = base_path('tests/Unit/TestConventionsUnitTest.php');
    if (file_exists($unitTestPath)) {
        $unitContent = file_get_contents($unitTestPath);

        // Test should validate fillable fields properly (fallback to 'name' when no schema)
        expect($unitContent)->toContain("['name']");

        // Test should use factory for creation
        expect($unitContent)->toContain('::factory()->make()->toArray()');

        // Clean up
        unlink($unitTestPath);
    }

    // Clean up other generated files
    $modelPath = app_path('Models/TestConventions.php');
    if (file_exists($modelPath)) {
        unlink($modelPath);
    }

    $migrationFiles = File::glob(database_path('migrations/*_create_test_conventions_table.php'));
    foreach ($migrationFiles as $file) {
        unlink($file);
    }

    $factoryPath = database_path('factories/TestConventionsFactory.php');
    if (file_exists($factoryPath)) {
        unlink($factoryPath);
    }
});

it('validates that tests use proper Laravel conventions', function () {
    // Generate a simple module
    $this->artisan('turbo:make ConventionExample --tests --force')
        ->assertExitCode(0);

    // Verify feature test follows Laravel conventions
    $featureTestPath = base_path('tests/Feature/ConventionExampleTest.php');
    if (file_exists($featureTestPath)) {
        $content = file_get_contents($featureTestPath);

        // Should use proper route naming
        expect($content)->toContain("route('convention-examples.store')");
        expect($content)->toContain("route('convention-examples.show'");
        expect($content)->toContain("route('convention-examples.update'");
        expect($content)->toContain("route('convention-examples.destroy'");

        // Should use proper table naming
        expect($content)->toContain('convention_examples');

        // Should use proper variable naming
        expect($content)->toContain('$conventionExample');

        // Clean up
        unlink($featureTestPath);
    }

    // Verify unit test structure
    $unitTestPath = base_path('tests/Unit/ConventionExampleUnitTest.php');
    if (file_exists($unitTestPath)) {
        $content = file_get_contents($unitTestPath);

        // Should test fillable attributes
        expect($content)->toContain('test_convention_example_has_fillable_attributes');

        // Should test model creation
        expect($content)->toContain('test_convention_example_can_be_created');

        // Should test timestamps
        expect($content)->toContain('test_convention_example_has_timestamps');

        // Clean up
        unlink($unitTestPath);
    }

    // Clean up generated files
    $filesToClean = [
        app_path('Models/ConventionExample.php'),
        database_path('factories/ConventionExampleFactory.php'),
    ];

    foreach ($filesToClean as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    $migrationFiles = File::glob(database_path('migrations/*_create_convention_examples_table.php'));
    foreach ($migrationFiles as $file) {
        unlink($file);
    }
});
