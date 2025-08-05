<?php

declare(strict_types=1);

namespace Tests;

use Grazulex\LaravelTurbomaker\LaravelTurbomakerServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class TestCase extends Orchestra
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Execute migration if needed
        // $this->artisan('migrate', ['--database' => 'testing']);
    }

    final public function debugToFile(string $content, string $context = ''): void
    {
        $file = base_path('turbomaker_test.log');
        $tag = $context !== '' && $context !== '0' ? "=== $context ===\n" : '';
        File::append($file, $tag.$content."\n");
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup Turbomaker specific testing environment
        $app['config']->set('turbomaker.enabled', true);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Use array cache for testing to avoid database cache issues
        $app['config']->set('cache.default', 'array');

        // Disable schema cache for testing
        $app['config']->set('turbomaker.schemas.cache_enabled', false);

        // Create necessary directories for testing
        $directories = [
            $app->basePath('app/Models'),
            $app->basePath('app/Http/Controllers'),
            $app->basePath('app/Http/Controllers/Api'),
            $app->basePath('app/Http/Requests'),
            $app->basePath('app/Http/Resources'),
            $app->basePath('app/Policies'),
            $app->basePath('database/factories'),
            $app->basePath('database/seeders'),
            $app->basePath('database/migrations'),
            $app->basePath('tests/Feature'),
            $app->basePath('tests/Unit'),
            $app->basePath('resources/views'),
            $app->basePath('resources/schemas'),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelTurbomakerServiceProvider::class,
        ];
    }
}
