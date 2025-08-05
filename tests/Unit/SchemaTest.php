<?php

declare(strict_types=1);

use Grazulex\LaravelTurbomaker\Schema\Field;
use Grazulex\LaravelTurbomaker\Schema\Relationship;
use Grazulex\LaravelTurbomaker\Schema\Schema;
use Grazulex\LaravelTurbomaker\TurboSchemaManager;

it('can create a field with basic properties', function () {
    $field = new Field(
        name: 'title',
        type: 'string',
        nullable: false,
        unique: false,
        length: 255
    );

    expect($field->name)->toBe('title');
    expect($field->type)->toBe('string');
    expect($field->nullable)->toBeFalse();
    expect($field->length)->toBe(255);
});

it('can create a field from array configuration', function () {
    $config = [
        'type' => 'string',
        'length' => 255,
        'nullable' => false,
        'unique' => true,
        'validation' => ['min:3', 'max:255'],
    ];

    $field = Field::fromArray('title', $config);

    expect($field->name)->toBe('title');
    expect($field->type)->toBe('string');
    expect($field->unique)->toBeTrue();
    expect($field->validationRules)->toBe(['min:3', 'max:255']);
});

it('can generate migration definition for a field', function () {
    $field = new Field(
        name: 'title',
        type: 'string',
        nullable: true,
        unique: true,
        length: 255
    );

    // Test that the field type is correct
    $definition = $field->getMigrationDefinition();
    expect($definition)->toBe('string');

    // Test that modifiers are generated correctly
    $modifiers = $field->getMigrationModifiers();
    expect($modifiers)->toContain('nullable()');
    expect($modifiers)->toContain('unique()');
});

it('can create a relationship', function () {
    $relationship = new Relationship(
        name: 'author',
        type: 'belongsTo',
        model: 'App\\Models\\User',
        foreignKey: 'user_id'
    );

    expect($relationship->name)->toBe('author');
    expect($relationship->type)->toBe('belongsTo');
    expect($relationship->model)->toBe('App\\Models\\User');
});

it('can generate model relationship definition', function () {
    $relationship = new Relationship(
        name: 'author',
        type: 'belongsTo',
        model: 'App\\Models\\User'
    );

    $definition = $relationship->getModelDefinition();

    expect($definition)->toContain('belongsTo');
    expect($definition)->toContain('App\\Models\\User::class');
});

it('can create a schema from array', function () {
    $config = [
        'fields' => [
            'title' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'content' => [
                'type' => 'text',
                'nullable' => true,
            ],
        ],
        'relationships' => [
            'author' => [
                'type' => 'belongsTo',
                'model' => 'App\\Models\\User',
            ],
        ],
        'options' => [
            'table' => 'posts',
            'timestamps' => true,
        ],
    ];

    $schema = Schema::fromArray('Post', $config);

    expect($schema->name)->toBe('Post');
    expect($schema->fields)->toHaveCount(2);
    expect($schema->relationships)->toHaveCount(1);
    expect($schema->getTableName())->toBe('posts');
});

it('can parse fields shorthand', function () {
    $schemaManager = new TurboSchemaManager();

    $schema = $schemaManager->resolveSchema('name:string,email:email:unique,age:integer:nullable', 'User');

    expect($schema)->not->toBeNull();
    expect($schema->fields)->toHaveCount(3);
    expect($schema->fields['name']->type)->toBe('string');
    expect($schema->fields['email']->unique)->toBeTrue();
    expect($schema->fields['age']->nullable)->toBeTrue();
});

it('can validate schema configuration', function () {
    $schemaManager = new TurboSchemaManager();

    $config = [
        'fields' => [
            'title' => [
                'type' => 'string',
            ],
        ],
    ];

    $schema = Schema::fromArray('Test', $config);
    $errors = $schemaManager->validateSchema($schema);

    expect($errors)->toBeEmpty();
});

it('detects invalid field types', function () {
    $schemaManager = new TurboSchemaManager();

    $config = [
        'fields' => [
            'title' => [
                'type' => 'invalid_type',
            ],
        ],
    ];

    $schema = Schema::fromArray('Test', $config);
    $errors = $schemaManager->validateSchema($schema);

    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('Invalid field type');
});

it('can generate context for stubs', function () {
    $config = [
        'fields' => [
            'title' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'is_active' => [
                'type' => 'boolean',
                'default' => true,
            ],
        ],
        'options' => [
            'timestamps' => true,
            'soft_deletes' => false,
        ],
    ];

    $schema = Schema::fromArray('Post', $config);
    $context = $schema->generateContext();

    expect($context)->toHaveKey('schema_fillable');
    expect($context)->toHaveKey('schema_casts');
    expect($context)->toHaveKey('schema_table_name');
    expect($context['schema_has_timestamps'])->toBeTrue();
    expect($context['schema_has_soft_deletes'])->toBeFalse();
});
