<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class ConfigurationTest extends TestCase
{
    public function test_default_configuration_structure(): void
    {
        $config = config('turbomaker');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('defaults', $config);
        $this->assertArrayHasKey('status_tracking', $config);
        $this->assertArrayHasKey('scanners', $config);
        $this->assertArrayHasKey('performance', $config);
        $this->assertArrayHasKey('export', $config);
    }

    public function test_defaults_configuration(): void
    {
        $defaults = config('turbomaker.defaults');

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('format', $defaults);
        $this->assertArrayHasKey('include_metadata', $defaults);
        $this->assertArrayHasKey('show_progress', $defaults);
        $this->assertArrayHasKey('output_path', $defaults);

        // Test generation defaults
        $this->assertArrayHasKey('generate_tests', $defaults);
        $this->assertArrayHasKey('generate_factory', $defaults);
        $this->assertArrayHasKey('generate_seeder', $defaults);
        $this->assertArrayHasKey('generate_policies', $defaults);
        $this->assertArrayHasKey('generate_api_resources', $defaults);
        $this->assertArrayHasKey('generate_actions', $defaults);
        $this->assertArrayHasKey('generate_services', $defaults);
        $this->assertArrayHasKey('generate_rules', $defaults);
        $this->assertArrayHasKey('generate_observers', $defaults);

        $this->assertEquals('table', $defaults['format']);
        $this->assertTrue($defaults['include_metadata']);
        $this->assertTrue($defaults['show_progress']);
        $this->assertStringContainsString('turbomaker', $defaults['output_path']);

        // Test boolean defaults
        $this->assertTrue($defaults['generate_tests']);
        $this->assertTrue($defaults['generate_factory']);
        $this->assertFalse($defaults['generate_seeder']);
        $this->assertFalse($defaults['generate_policies']);
        $this->assertTrue($defaults['generate_api_resources']);
        $this->assertFalse($defaults['generate_actions']);
        $this->assertFalse($defaults['generate_services']);
        $this->assertFalse($defaults['generate_rules']);
        $this->assertFalse($defaults['generate_observers']);
    }

    public function test_status_tracking_configuration(): void
    {
        $statusTracking = config('turbomaker.status_tracking');

        $this->assertIsArray($statusTracking);
        $this->assertArrayHasKey('enabled', $statusTracking);
        $this->assertArrayHasKey('log_file', $statusTracking);

        $this->assertTrue($statusTracking['enabled']);
        $this->assertStringContainsString('turbomaker.log', $statusTracking['log_file']);
    }

    public function test_scanners_configuration(): void
    {
        $scanners = config('turbomaker.scanners');

        $this->assertIsArray($scanners);
        $this->assertArrayHasKey('routes', $scanners);
        $this->assertArrayHasKey('models', $scanners);
        $this->assertArrayHasKey('views', $scanners);

        // Test routes scanner config
        $routesConfig = $scanners['routes'];
        $this->assertTrue($routesConfig['include_middleware']);
        $this->assertTrue($routesConfig['show_unused']);

        // Test models scanner config
        $modelsConfig = $scanners['models'];
        $this->assertTrue($modelsConfig['include_relationships']);
        $this->assertTrue($modelsConfig['show_attributes']);

        // Test views scanner config
        $viewsConfig = $scanners['views'];
        $this->assertTrue($viewsConfig['check_unused']);
        $this->assertTrue($viewsConfig['include_components']);
    }

    public function test_performance_configuration(): void
    {
        $performance = config('turbomaker.performance');

        $this->assertIsArray($performance);
        $this->assertArrayHasKey('cache_enabled', $performance);
        $this->assertArrayHasKey('cache_ttl', $performance);
        $this->assertArrayHasKey('memory_limit', $performance);

        $this->assertTrue($performance['cache_enabled']);
        $this->assertEquals(3600, $performance['cache_ttl']);
        $this->assertEquals('512M', $performance['memory_limit']);
    }

    public function test_export_configuration(): void
    {
        $export = config('turbomaker.export');

        $this->assertIsArray($export);
        $this->assertArrayHasKey('formats', $export);
        $this->assertArrayHasKey('include_timestamps', $export);
        $this->assertArrayHasKey('compress_output', $export);

        $this->assertIsArray($export['formats']);
        $this->assertContains('json', $export['formats']);
        $this->assertContains('csv', $export['formats']);
        $this->assertContains('html', $export['formats']);

        $this->assertTrue($export['include_timestamps']);
        $this->assertFalse($export['compress_output']);
    }

    public function test_turbomaker_is_enabled_by_default(): void
    {
        $enabled = config('turbomaker.enabled');
        $this->assertTrue($enabled);
    }

    public function test_environment_variables_are_respected(): void
    {
        // Test that environment variables would override defaults
        $this->assertEquals('table', config('turbomaker.defaults.format'));
        $this->assertTrue(config('turbomaker.defaults.include_metadata'));
        $this->assertTrue(config('turbomaker.enabled'));
    }

    public function test_paths_configuration(): void
    {
        $paths = config('turbomaker.paths');

        $this->assertIsArray($paths);
        $this->assertArrayHasKey('models', $paths);
        $this->assertArrayHasKey('controllers', $paths);
        $this->assertArrayHasKey('api_controllers', $paths);
        $this->assertArrayHasKey('requests', $paths);
        $this->assertArrayHasKey('resources', $paths);
        $this->assertArrayHasKey('policies', $paths);
        $this->assertArrayHasKey('actions', $paths);
        $this->assertArrayHasKey('services', $paths);
        $this->assertArrayHasKey('rules', $paths);
        $this->assertArrayHasKey('observers', $paths);
        $this->assertArrayHasKey('migrations', $paths);
        $this->assertArrayHasKey('factories', $paths);
        $this->assertArrayHasKey('seeders', $paths);
        $this->assertArrayHasKey('views', $paths);
        $this->assertArrayHasKey('tests', $paths);
        $this->assertArrayHasKey('feature_tests', $paths);
        $this->assertArrayHasKey('unit_tests', $paths);

        $this->assertEquals('app/Models', $paths['models']);
        $this->assertEquals('app/Http/Controllers', $paths['controllers']);
        $this->assertEquals('app/Http/Controllers/Api', $paths['api_controllers']);
    }

    public function test_stubs_configuration(): void
    {
        $stubs = config('turbomaker.stubs');

        $this->assertIsArray($stubs);
        $this->assertArrayHasKey('path', $stubs);
        $this->assertArrayHasKey('templates', $stubs);

        $templates = $stubs['templates'];
        $this->assertIsArray($templates);
        $this->assertArrayHasKey('model', $templates);
        $this->assertArrayHasKey('controller', $templates);
        $this->assertArrayHasKey('api_controller', $templates);
        $this->assertArrayHasKey('migration', $templates);
        $this->assertArrayHasKey('factory', $templates);
        $this->assertArrayHasKey('seeder', $templates);
        $this->assertArrayHasKey('policy', $templates);
        $this->assertArrayHasKey('test_feature', $templates);
        $this->assertArrayHasKey('test_unit', $templates);

        $this->assertEquals('model.stub', $templates['model']);
        $this->assertEquals('controller.stub', $templates['controller']);
        $this->assertEquals('controller.api.stub', $templates['api_controller']);
    }
}
