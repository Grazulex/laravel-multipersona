<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('resolves schema files correctly with snake_case conversion', function () {
    // Create a test schema with snake_case filename
    $schemaContent = <<<'YAML'
fields:
  title:
    type: string
    nullable: false
  content:
    type: text
    nullable: true
  published_at:
    type: datetime
    nullable: true

options:
  table: blog_posts
  timestamps: true

metadata:
  version: "1.0"
  description: "Blog post schema for testing"
YAML;

    $schemaPath = resource_path('schemas');
    if (! File::exists($schemaPath)) {
        File::makeDirectory($schemaPath, 0755, true);
    }

    File::put($schemaPath.'/blog_post.schema.yml', $schemaContent);

    // Test that schema is found when using PascalCase name
    $this->artisan('turbo:make BlogPost --schema=BlogPost --force')
        ->expectsOutput('🚀 Generating Laravel module: BlogPost')
        ->expectsOutputToContain('✅ Schema resolved successfully')
        ->assertExitCode(0);

    // Verify that the generated model has correct fields in fillable
    $modelPath = app_path('Models/BlogPost.php');
    if (file_exists($modelPath)) {
        $modelContent = file_get_contents($modelPath);
        expect($modelContent)->toContain("'title'");
        expect($modelContent)->toContain("'content'");
        expect($modelContent)->toContain("'published_at'");

        // Clean up
        unlink($modelPath);
    }

    // Clean up migration if created
    $migrationFiles = File::glob(database_path('migrations/*_create_blog_posts_table.php'));
    foreach ($migrationFiles as $file) {
        unlink($file);
    }

    // Clean up factory if created
    $factoryPath = database_path('factories/BlogPostFactory.php');
    if (file_exists($factoryPath)) {
        unlink($factoryPath);
    }

    // Clean up schema file
    File::delete($schemaPath.'/blog_post.schema.yml');
});

it('shows clear error when schema is not found', function () {
    $this->artisan('turbo:make Product --schema=NonExistentSchema')
        ->expectsOutputToContain('⚠️  Schema \'NonExistentSchema\' not found in any of these locations:')
        ->expectsOutputToContain('Using default generation instead.')
        ->assertExitCode(0);
});

it('generates factory with correct field types', function () {
    // Create a test schema with diverse field types
    $schemaContent = <<<'YAML'
fields:
  name:
    type: string
    nullable: false
  email:
    type: email
    nullable: false
  age:
    type: integer
    nullable: true
  is_active:
    type: boolean
    nullable: false
    default: true
  bio:
    type: text
    nullable: true
  price:
    type: decimal
    nullable: false

options:
  table: test_models
  timestamps: true

metadata:
  version: "1.0"
  description: "Test model with diverse field types"
YAML;

    $schemaPath = resource_path('schemas');
    if (! File::exists($schemaPath)) {
        File::makeDirectory($schemaPath, 0755, true);
    }

    File::put($schemaPath.'/test_model.schema.yml', $schemaContent);

    // Generate with factory
    $this->artisan('turbo:make TestModel --schema=TestModel --factory --force')
        ->expectsOutputToContain('✅ Schema resolved successfully')
        ->assertExitCode(0);

    // Check factory content
    $factoryPath = database_path('factories/TestModelFactory.php');
    if (file_exists($factoryPath)) {
        $factoryContent = file_get_contents($factoryPath);

        // Verify different faker methods are used for different field types
        expect($factoryContent)->toContain('name'); // string field
        expect($factoryContent)->toContain('email'); // email field
        expect($factoryContent)->toContain('age'); // integer field
        expect($factoryContent)->toContain('is_active'); // boolean field
        expect($factoryContent)->toContain('bio'); // text field
        expect($factoryContent)->toContain('price'); // decimal field

        // Clean up
        unlink($factoryPath);
    }

    // Clean up other generated files
    $modelPath = app_path('Models/TestModel.php');
    if (file_exists($modelPath)) {
        unlink($modelPath);
    }

    $migrationFiles = File::glob(database_path('migrations/*_create_test_models_table.php'));
    foreach ($migrationFiles as $file) {
        unlink($file);
    }

    // Clean up schema file
    File::delete($schemaPath.'/test_model.schema.yml');
});
