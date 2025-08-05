<?php

declare(strict_types=1);

namespace Tests\Unit;

use Grazulex\LaravelTurbomaker\TurbomakerManager;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

final class TurbomakerManagerTest extends TestCase
{
    private TurbomakerManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(TurbomakerManager::class);
    }

    public function test_manager_can_get_application(): void
    {
        $app = $this->manager->getApplication();
        $this->assertSame($this->app, $app);
    }

    public function test_manager_is_enabled_by_default(): void
    {
        $this->assertTrue($this->manager->isEnabled());
    }

    public function test_manager_can_be_disabled(): void
    {
        config(['turbomaker.enabled' => false]);
        $this->assertFalse($this->manager->isEnabled());
    }

    public function test_manager_can_get_defaults(): void
    {
        $defaults = $this->manager->getDefaults();
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('format', $defaults);
        $this->assertArrayHasKey('include_metadata', $defaults);
    }

    public function test_manager_can_register_scanner(): void
    {
        $scanner = fn (array $options = []) => ['test' => 'data'];
        $this->manager->registerScanner('test', $scanner);

        $scanners = $this->manager->getScanners();
        $this->assertArrayHasKey('test', $scanners);
        $this->assertSame($scanner, $scanners['test']);
    }

    public function test_manager_can_run_scanner(): void
    {
        $testData = ['test' => 'data', 'items' => [1, 2, 3]];
        $scanner = fn (array $options = []) => $testData;

        $this->manager->registerScanner('test', $scanner);
        $result = $this->manager->runScanner('test');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals($testData, $result->toArray());
    }

    public function test_manager_throws_exception_for_unknown_scanner(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Scanner 'unknown' not found.");

        $this->manager->runScanner('unknown');
    }

    public function test_manager_can_get_status_tracking(): void
    {
        $statusTracking = $this->manager->getStatusTracking();
        $this->assertIsArray($statusTracking);
        $this->assertArrayHasKey('enabled', $statusTracking);
    }

    public function test_manager_status_tracking_is_enabled_by_default(): void
    {
        $this->assertTrue($this->manager->isStatusTrackingEnabled());
    }

    public function test_manager_can_get_scanner_config(): void
    {
        $routesConfig = $this->manager->getScannerConfig('routes');
        $this->assertIsArray($routesConfig);
        $this->assertArrayHasKey('include_middleware', $routesConfig);
    }

    public function test_manager_can_get_performance_settings(): void
    {
        $performance = $this->manager->getPerformanceSettings();
        $this->assertIsArray($performance);
        $this->assertArrayHasKey('cache_enabled', $performance);
    }

    public function test_manager_can_get_export_config(): void
    {
        $export = $this->manager->getExportConfig();
        $this->assertIsArray($export);
        $this->assertArrayHasKey('formats', $export);
        $this->assertIsArray($export['formats']);
    }
}
