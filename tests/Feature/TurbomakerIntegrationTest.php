<?php

declare(strict_types=1);

namespace Tests\Feature;

use Grazulex\LaravelTurbomaker\TurbomakerManager;
use Tests\TestCase;

final class TurbomakerIntegrationTest extends TestCase
{
    public function test_turbomaker_facade_works(): void
    {
        $manager = app('turbomaker');
        $this->assertInstanceOf(TurbomakerManager::class, $manager);
    }

    public function test_turbomaker_config_can_be_published(): void
    {
        $this->artisan('vendor:publish', [
            '--tag' => 'turbomaker-config',
            '--force' => true,
        ])->assertExitCode(0);

        $configPath = config_path('turbomaker.php');
        $this->assertFileExists($configPath);

        // Clean up
        if (file_exists($configPath)) {
            unlink($configPath);
        }
    }

    public function test_turbomaker_stubs_can_be_published(): void
    {
        $this->artisan('vendor:publish', [
            '--tag' => 'turbomaker-stubs',
            '--force' => true,
        ])->assertExitCode(0);

        $stubsPath = resource_path('stubs/turbomaker');
        $this->assertDirectoryExists($stubsPath);

        // Check that some key stub files exist
        $this->assertFileExists($stubsPath.'/model.stub');
        $this->assertFileExists($stubsPath.'/controller.stub');
        $this->assertFileExists($stubsPath.'/migration.stub');

        // Clean up recursively
        if (is_dir($stubsPath)) {
            $files = glob($stubsPath.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($stubsPath);

            // Also remove the stubs directory if it's empty
            $stubsParentPath = resource_path('stubs');
            if (is_dir($stubsParentPath) && count(glob($stubsParentPath.'/*')) === 0) {
                rmdir($stubsParentPath);
            }
        }
    }

    public function test_turbomaker_manager_integration_with_scanners(): void
    {
        $manager = app('turbomaker');

        // Test scanner registration and execution
        $testScanner = function (array $options = []) {
            return [
                'scanned_at' => now()->toISOString(),
                'items_found' => count($options),
                'options' => $options,
            ];
        };

        $manager->registerScanner('integration_test', $testScanner);

        $result = $manager->runScanner('integration_test', ['param1' => 'value1', 'param2' => 'value2']);

        $this->assertIsObject($result);
        $this->assertTrue($result->has('scanned_at'));
        $this->assertEquals(2, $result->get('items_found'));
        $this->assertEquals(['param1' => 'value1', 'param2' => 'value2'], $result->get('options'));
    }

    public function test_turbomaker_configuration_is_loaded_correctly(): void
    {
        $manager = app('turbomaker');

        // Test that all config sections are available
        $this->assertTrue($manager->isEnabled());
        $this->assertIsArray($manager->getDefaults());
        $this->assertIsArray($manager->getStatusTracking());
        $this->assertIsArray($manager->getPerformanceSettings());
        $this->assertIsArray($manager->getExportConfig());

        // Test specific config values
        $defaults = $manager->getDefaults();
        $this->assertEquals('table', $defaults['format']);
        $this->assertTrue($defaults['include_metadata']);

        $performance = $manager->getPerformanceSettings();
        $this->assertTrue($performance['cache_enabled']);
        $this->assertEquals(3600, $performance['cache_ttl']);
    }

    public function test_turbomaker_scanner_config_works(): void
    {
        $manager = app('turbomaker');

        $routesConfig = $manager->getScannerConfig('routes');
        $this->assertIsArray($routesConfig);
        $this->assertTrue($routesConfig['include_middleware']);
        $this->assertTrue($routesConfig['show_unused']);

        $modelsConfig = $manager->getScannerConfig('models');
        $this->assertIsArray($modelsConfig);
        $this->assertTrue($modelsConfig['include_relationships']);
        $this->assertTrue($modelsConfig['show_attributes']);
    }
}
