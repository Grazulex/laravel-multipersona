<?php

declare(strict_types=1);

use Grazulex\LaravelTurbomaker\Adapters\ModelSchemaGenerationAdapter;
use Grazulex\LaravelTurbomaker\Generators\ModuleGenerator;

it('writes actual files when write_files option is enabled', function () {
    // Create a test module with file writing enabled
    $generator = new ModuleGenerator();

    $options = [
        'write_files' => true, // Enable hybrid mode: Fragment Architecture + File Writing
        'generate_requests' => true,
        'generate_factory' => true,
        'api_only' => false,
    ];

    $results = $generator->generateWithFiles('TestModel', $options);

    // Verify that file paths are returned
    expect($results)->toBeArray();
    expect($results)->toHaveKey('model');
    expect($results)->toHaveKey('migration');

    // Verify that actual files were created
    expect($results['model'])->toBeArray();
    expect($results['model'])->toHaveCount(1);

    $modelPath = $results['model'][0];
    expect($modelPath)->toEndWith('TestModel.php');
    expect(file_exists($modelPath))->toBeTrue();

    // Verify file content contains expected PHP code
    $modelContent = file_get_contents($modelPath);
    expect($modelContent)->toContain('<?php');
    expect($modelContent)->toContain('class TestModel');
    expect($modelContent)->toContain('extends Model');

    // Check migration file
    expect($results['migration'])->toBeArray();
    expect($results['migration'])->toHaveCount(1);

    $migrationPath = $results['migration'][0];
    expect(file_exists($migrationPath))->toBeTrue();

    $migrationContent = file_get_contents($migrationPath);
    expect($migrationContent)->toContain('<?php');
    expect($migrationContent)->toContain('Schema::create');
    expect($migrationContent)->toContain('test_models');

    // Clean up created files
    if (file_exists($modelPath)) {
        unlink($modelPath);
    }
    if (file_exists($migrationPath)) {
        unlink($migrationPath);
    }
});

it('works with pure fragment architecture when write_files is false', function () {
    // Test pure Fragment Architecture (no file writing)
    $adapter = new ModelSchemaGenerationAdapter();

    $options = [
        'write_files' => false, // Pure Fragment Architecture
        'generate_requests' => true,
        'generate_factory' => true,
    ];

    $results = $adapter->generateAllFragments('FragmentModel', $options);

    // Verify that file paths are simulated (not real files)
    expect($results)->toBeArray();
    expect($results)->toHaveKey('model');
    expect($results)->toHaveKey('migration');

    // Verify paths are returned but files don't exist
    $modelPath = $results['model'][0];
    expect($modelPath)->toEndWith('FragmentModel.php');

    // Check if files were actually created (they shouldn't be in fragment mode)
    $modelExists = file_exists($modelPath);
    if ($modelExists) {
        // Clean up if file was created unexpectedly
        @unlink($modelPath);
    }
    expect($modelExists)->toBeFalse(); // No actual file created

    $migrationPath = $results['migration'][0];
    $migrationExists = file_exists($migrationPath);
    if ($migrationExists) {
        // Clean up if file was created unexpectedly
        @unlink($migrationPath);
    }
    expect($migrationExists)->toBeFalse(); // No actual file created
});
