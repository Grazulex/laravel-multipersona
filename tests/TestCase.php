<?php

declare(strict_types=1);

namespace Tests;

use Grazulex\LaravelMultiPersona\LaravelMultiPersonaServiceProvider;
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
        $this->artisan('migrate', ['--database' => 'testing']);
    }

    final public function debugToFile(string $content, string $context = ''): void
    {
        $file = base_path('multipersona_test.log');
        $tag = $context !== '' && $context !== '0' ? "=== $context ===\n" : '';
        File::append($file, $tag.$content."\n");
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup MultiPersona specific testing environment
        $app['config']->set('multipersona.user_model', 'App\Models\User');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Use array cache for testing to avoid database cache issues
        $app['config']->set('cache.default', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelMultiPersonaServiceProvider::class,
        ];
    }
}
