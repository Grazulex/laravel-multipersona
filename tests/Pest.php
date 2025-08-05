<?php

declare(strict_types=1);

use Grazulex\LaravelMultiPersona\LaravelMultiPersonaServiceProvider;
use Orchestra\Testbench\TestCase;

uses(TestCase::class)->in('Feature', 'Unit', 'Integration');

// Configure the package for testing
uses()->beforeEach(function (): void {
    $this->app->register(LaravelMultiPersonaServiceProvider::class);
})->in('Feature', 'Unit', 'Integration');

// Define test groups for migration
// uses()->group('migration')->in('Unit/ModelSchemaIntegrationTest.php');
