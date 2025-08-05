<?php

declare(strict_types=1);

use Grazulex\LaravelTurbomaker\Adapters\ModelSchemaGenerationAdapter;

it('debugs ModelSchema fragment structure', function () {
    $adapter = new ModelSchemaGenerationAdapter();

    // Get raw results from ModelSchema
    $modelSchema = $adapter->getGenerationService();

    try {
        // Try to generate with ModelSchema directly
        $schema = Grazulex\LaravelModelschema\Schema\ModelSchema::fromArray('DebugModel', [
            'table' => 'debug_models',
            'fields' => [
                'name' => [
                    'type' => 'string',
                    'nullable' => false,
                    'validation' => ['required', 'string', 'max:255'],
                ],
            ],
            'relationships' => [],
            'options' => [
                'timestamps' => true,
                'soft_deletes' => false,
            ],
        ]);

        $options = [
            'model' => true,
            'migration' => true,
            'enhanced' => true,
        ];

        $results = $modelSchema->generateAll($schema, $options);

        // Verify structure exists instead of echoing debug info for CI compatibility
        expect($results)->toBeArray();
        expect($results)->toHaveKeys(['model', 'migration']);

        foreach ($results as $type => $result) {
            if (is_array($result)) {
                expect($result)->toHaveKey('metadata');

                if (isset($result['metadata'])) {
                    expect($result['metadata'])->toHaveKey('generator');
                    expect($result['metadata'])->toHaveKey('model_name', 'DebugModel');
                }
            }
        }

    } catch (Exception $e) {
        // Fail the test if there's an exception
        expect($e->getMessage())->toBeEmpty("ModelSchema generation failed: {$e->getMessage()}");
    }

    // Verify the test actually validates something meaningful
    expect($results)->not->toBeEmpty();
});
