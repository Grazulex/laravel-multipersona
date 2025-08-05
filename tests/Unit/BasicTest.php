<?php

declare(strict_types=1);

namespace Tests\Unit;

use Grazulex\LaravelTurbomaker\LaravelTurbomakerServiceProvider;
use Grazulex\LaravelTurbomaker\TurbomakerManager;
use Orchestra\Testbench\TestCase;

final class BasicTest extends TestCase
{
    public function test_service_provider_is_loaded(): void
    {
        $this->assertNotNull($this->app);
        // Test that the service provider is registered
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(LaravelTurbomakerServiceProvider::class, $providers);
    }

    public function test_turbomaker_manager_is_bound(): void
    {
        $this->assertTrue($this->app->bound(TurbomakerManager::class));
        $this->assertTrue($this->app->bound('turbomaker'));
    }

    public function test_turbomaker_manager_can_be_resolved(): void
    {
        $manager = $this->app->make(TurbomakerManager::class);
        $this->assertInstanceOf(TurbomakerManager::class, $manager);

        $managerAlias = $this->app->make('turbomaker');
        $this->assertInstanceOf(TurbomakerManager::class, $managerAlias);
        $this->assertSame($manager, $managerAlias);
    }

    public function test_config_is_available(): void
    {
        $this->assertTrue(config('turbomaker.status_tracking.enabled', true));
    }

    public function test_package_configuration_is_available(): void
    {
        $this->assertNotNull($this->app);
        // Test that config is properly merged
        $this->assertIsArray(config('turbomaker'));
        $this->assertArrayHasKey('defaults', config('turbomaker'));

        // Test defaults config structure
        $defaults = config('turbomaker.defaults');
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('format', $defaults);
        $this->assertArrayHasKey('include_metadata', $defaults);
    }

    public function test_turbomaker_is_enabled_by_default(): void
    {
        $manager = $this->app->make(TurbomakerManager::class);
        $this->assertTrue($manager->isEnabled());
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelTurbomakerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup the application environment for testing
        $app['config']->set('turbomaker.enabled', true);
    }
}
