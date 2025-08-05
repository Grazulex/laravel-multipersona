<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

uses()->group('commands');

beforeEach(function () {
    // Ensure schema directory exists
    $schemaPath = resource_path('schemas');
    if (! File::exists($schemaPath)) {
        File::makeDirectory($schemaPath, 0755, true);
    }
});

afterEach(function () {
    // Simple cleanup
    $schemaPath = resource_path('schemas');
    if (File::exists($schemaPath)) {
        File::deleteDirectory($schemaPath);
    }
});

it('can generate module with fields shorthand', function () {
    $this->artisan('turbo:make TestPost --fields="title:string,content:text,is_published:boolean" --force')
        ->expectsOutput('✅ Schema resolved successfully')
        ->expectsOutput('🚀 Generating Laravel module: TestPost')
        ->expectsOutput("✅ Module 'TestPost' generated successfully!")
        ->assertExitCode(0);
});

it('can generate module with schema file', function () {
    // Create a test schema file
    $schemaContent = <<<YAML
fields:
  name:
    type: string
    nullable: false
    validation:
      - "min:3"
      - "max:255"
  
  description:
    type: text
    nullable: true
  
  price:
    type: decimal
    length: 8,2
    nullable: false
    default: 0.00

relationships:
  category:
    type: belongsTo
    model: App\Models\Category

options:
  table: products
  timestamps: true
  soft_deletes: false

metadata:
  version: "1.0"
  description: "Product schema"
YAML;

    File::put(resource_path('schemas/product.schema.yml'), $schemaContent);

    $this->artisan('turbo:make Product --schema=product --force')
        ->expectsOutput('🚀 Generating Laravel module: Product')
        ->expectsOutput('📋 Using schema: Product')
        ->expectsOutput('Fields: 3, Relations: 1')
        ->expectsOutput("✅ Module 'Product' generated successfully!")
        ->assertExitCode(0);
});

it('can generate module without schema (fallback)', function () {
    $this->artisan('turbo:make SimpleModel --force')
        ->expectsOutput('🚀 Generating Laravel module: SimpleModel')
        ->expectsOutput("✅ Module 'SimpleModel' generated successfully!")
        ->assertExitCode(0);
});

it('shows warning when schema not found', function () {
    $this->artisan('turbo:make TestModel --schema=nonexistent --force')
        ->expectsOutputToContain('⚠️  Schema \'nonexistent\' not found in any of these locations:')
        ->expectsOutputToContain('Using default generation instead.')
        ->expectsOutput("✅ Module 'TestModel' generated successfully!")
        ->assertExitCode(0);
});

it('handles invalid fields shorthand gracefully', function () {
    $this->artisan('turbo:make TestModel --fields="name:invalid_type,email:string" --force')
        ->expectsOutput('❌ Schema error: Invalid field type \'invalid_type\' for field \'name\'')
        ->assertExitCode(1);
});

it('can generate with inline schema', function () {
    $inlineSchema = 'fields: { title: { type: string }, content: { type: text } }';

    $this->artisan("turbo:make BlogPost --schema=\"{$inlineSchema}\" --force")
        ->expectsOutput('✅ Schema resolved successfully')
        ->expectsOutput('🚀 Generating Laravel module: BlogPost')
        ->expectsOutput("✅ Module 'BlogPost' generated successfully!")
        ->assertExitCode(0);
});

it('displays schema information during generation', function () {
    $this->artisan('turbo:make TestPost --fields="title:string:unique,content:text:nullable,published_at:datetime" --force')
        ->expectsOutput('📋 Schema details:')
        ->expectsOutputToContain('Fields:')
        ->expectsOutputToContain('title: string (unique)')
        ->expectsOutputToContain('content: text (nullable)')
        ->expectsOutputToContain('published_at: datetime')
        ->assertExitCode(0);
});
