<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

uses()->group('commands');

beforeEach(function () {
    // Clean up first, then ensure schema directory exists
    $schemaPath = resource_path('schemas');
    if (File::exists($schemaPath)) {
        File::deleteDirectory($schemaPath);
    }
    File::makeDirectory($schemaPath, 0755, true);
});

afterEach(function () {
    // Clean up test schemas
    $schemaPath = resource_path('schemas');
    if (File::exists($schemaPath)) {
        File::deleteDirectory($schemaPath);
    }
});

it('can list schemas when none exist', function () {
    $this->artisan('turbo:schema list')
        ->expectsOutput('📋 Available schemas:')
        ->expectsOutput('  No schemas found')
        ->assertExitCode(0);
});

it('can create a basic schema', function () {
    $this->artisan('turbo:schema create TestModel')
        ->expectsOutput("✅ Schema 'TestModel' created successfully!")
        ->assertExitCode(0);

    $schemaPath = resource_path('schemas/test_model.schema.yml');
    expect(File::exists($schemaPath))->toBeTrue();

    $content = File::get($schemaPath);
    expect($content)->toContain('fields:');
    expect($content)->toContain('name:');
});

it('can create schema with fields option', function () {
    $this->artisan('turbo:schema create BlogPost --fields="title:string,content:text,is_published:boolean"')
        ->expectsOutput("✅ Schema 'BlogPost' created successfully!")
        ->assertExitCode(0);

    $schemaPath = resource_path('schemas/blog_post.schema.yml');
    expect(File::exists($schemaPath))->toBeTrue();

    $content = File::get($schemaPath);
    expect($content)->toContain('title:');
    expect($content)->toContain('content:');
    expect($content)->toContain('is_published:');
});

it('can create schema with template', function () {
    $this->artisan('turbo:schema create Product --template=ecommerce')
        ->expectsOutput("✅ Schema 'Product' created successfully!")
        ->assertExitCode(0);

    $schemaPath = resource_path('schemas/product.schema.yml');
    expect(File::exists($schemaPath))->toBeTrue();

    $content = File::get($schemaPath);
    expect($content)->toContain('price:');
    expect($content)->toContain('stock_quantity:');
});

it('prevents overwriting existing schema without force', function () {
    // Create a schema first
    $this->artisan('turbo:schema create TestModel');

    // Try to create again without force
    $this->artisan('turbo:schema create TestModel')
        ->expectsOutput("Schema 'TestModel' already exists. Use --force to overwrite.")
        ->assertExitCode(1);
});

it('can overwrite existing schema with force', function () {
    // Create a schema first
    $this->artisan('turbo:schema create TestModel');

    // Overwrite with force
    $this->artisan('turbo:schema create TestModel --force')
        ->expectsOutput("✅ Schema 'TestModel' created successfully!")
        ->assertExitCode(0);
});

it('can show schema details', function () {
    // Create a test schema file
    $schemaPath = resource_path('schemas');

    $schemaContent = <<<YAML
fields:
  title:
    type: string
    nullable: false
  content:
    type: text
    nullable: true

relationships:
  author:
    type: belongsTo
    model: App\Models\User

options:
  table: posts
  timestamps: true

metadata:
  version: "1.0"
  description: "Test post schema"
YAML;

    File::put($schemaPath.'/test_post.schema.yml', $schemaContent);

    $this->artisan('turbo:schema show test_post')
        ->expectsOutput('📋 Schema: TestPost')
        ->expectsOutput('Fields:')
        ->expectsOutput('Relationships:')
        ->assertExitCode(0);
});

it('can validate a valid schema', function () {
    // Create a test schema file
    $schemaPath = resource_path('schemas');

    $schemaContent = <<<'YAML'
fields:
  title:
    type: string
    nullable: false

options:
  table: posts
YAML;

    File::put($schemaPath.'/valid_test.schema.yml', $schemaContent);

    $this->artisan('turbo:schema validate valid_test')
        ->expectsOutput("✅ Schema 'ValidTest' is valid!")
        ->assertExitCode(0);
});

it('can detect invalid schema', function () {
    // Create a test schema file with invalid field type
    $schemaPath = resource_path('schemas');

    $schemaContent = <<<'YAML'
fields:
  title:
    type: invalid_type
    nullable: false
YAML;

    File::put($schemaPath.'/invalid_test.schema.yml', $schemaContent);

    $this->artisan('turbo:schema validate invalid_test')
        ->expectsOutput('❌ Failed to validate schema: Invalid field type \'invalid_type\' for field \'title\'')
        ->assertExitCode(1);
});

it('can clear schema cache', function () {
    $this->artisan('turbo:schema clear-cache')
        ->expectsOutput('✅ Schema cache cleared successfully!')
        ->assertExitCode(0);
});

it('handles invalid action', function () {
    $this->artisan('turbo:schema invalid-action')
        ->expectsOutput('Invalid action: invalid-action')
        ->expectsOutput('Available actions: list, create, show, validate, diff, optimize, clear-cache')
        ->assertExitCode(1);
});
