<?php

declare(strict_types=1);

use Grazulex\LaravelTurbomaker\LaravelTurbomakerServiceProvider;
use Orchestra\Testbench\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// Configure the package for testing
uses()->beforeEach(function (): void {
    $this->app->register(LaravelTurbomakerServiceProvider::class);
})->in('Feature', 'Unit');

// Define test groups for migration
// uses()->group('migration')->in('Unit/ModelSchemaIntegrationTest.php');
