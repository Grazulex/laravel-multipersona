<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ConfigurationUsageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    public function test_generator_respects_default_configurations(): void
    {
        // Test that defaults are respected when no options are provided
        $this->artisan('turbo:make', [
            'name' => 'ConfigTestModel',
            '--force' => true,
        ])->assertExitCode(0);

        // Should generate tests by default (generate_tests = true)
        $this->assertFileExists(base_path('tests/Feature/ConfigTestModelTest.php'));
        $this->assertFileExists(base_path('tests/Unit/ConfigTestModelUnitTest.php'));

        // Should generate factory by default (generate_factory = true)
        $this->assertFileExists(database_path('factories/ConfigTestModelFactory.php'));

        // Should NOT generate seeder by default (generate_seeder = false)
        $this->assertFileDoesNotExist(database_path('seeders/ConfigTestModelSeeder.php'));

        // Should NOT generate policies by default (generate_policies = false)
        $this->assertFileDoesNotExist(app_path('Policies/ConfigTestModelPolicy.php'));

        // Should NOT generate actions by default (generate_actions = false)
        $this->assertFileDoesNotExist(app_path('Actions/CreateConfigTestModelAction.php'));

        // Should NOT generate services by default (generate_services = false)
        $this->assertFileDoesNotExist(app_path('Services/ConfigTestModelService.php'));

        // Should NOT generate rules by default (generate_rules = false)
        $this->assertFileDoesNotExist(app_path('Rules/ExistsConfigTestModelRule.php'));

        // Should NOT generate observers by default (generate_observers = false)
        $this->assertFileDoesNotExist(app_path('Observers/ConfigTestModelObserver.php'));
    }

    public function test_api_command_respects_default_configurations(): void
    {
        // Test API command with default configurations
        $this->artisan('turbo:make', [
            'name' => 'ApiConfigTest',
            '--api' => true,
            '--force' => true,
        ])->assertExitCode(0);

        // Should generate tests by default (generate_tests = true)
        $this->assertFileExists(base_path('tests/Feature/ApiConfigTestTest.php'));
        $this->assertFileExists(base_path('tests/Unit/ApiConfigTestUnitTest.php'));

        // Should generate factory by default (generate_factory = true)
        $this->assertFileExists(database_path('factories/ApiConfigTestFactory.php'));

        // Should generate API resources by default (generate_api_resources = true)
        $this->assertFileExists(app_path('Http/Resources/ApiConfigTestResource.php'));

        // Should NOT generate views (API-only)
        $this->assertDirectoryDoesNotExist(resource_path('views/api_config_tests'));
    }

    public function test_config_overrides_work_correctly(): void
    {
        // Temporarily override config for this test
        config(['turbomaker.defaults.generate_seeder' => true]);
        config(['turbomaker.defaults.generate_policies' => true]);

        $this->artisan('turbo:make', [
            'name' => 'OverrideTest',
            '--force' => true,
        ])->assertExitCode(0);

        // Should generate seeder due to config override
        $this->assertFileExists(database_path('seeders/OverrideTestSeeder.php'));

        // Should generate policy due to config override
        $this->assertFileExists(app_path('Policies/OverrideTestPolicy.php'));

        // Reset config
        config(['turbomaker.defaults.generate_seeder' => false]);
        config(['turbomaker.defaults.generate_policies' => false]);
    }

    public function test_explicit_options_override_config_defaults(): void
    {
        // Even though config says generate_seeder = false, explicit --seeder should work
        $this->artisan('turbo:make', [
            'name' => 'ExplicitTest',
            '--seeder' => true,
            '--policies' => true,
            '--actions' => true,
            '--services' => true,
            '--rules' => true,
            '--observers' => true,
            '--force' => true,
        ])->assertExitCode(0);

        // All should be generated despite defaults
        $this->assertFileExists(database_path('seeders/ExplicitTestSeeder.php'));
        $this->assertFileExists(app_path('Policies/ExplicitTestPolicy.php'));
        $this->assertFileExists(app_path('Actions/CreateExplicitTestAction.php'));
        $this->assertFileExists(app_path('Services/ExplicitTestService.php'));
        $this->assertFileExists(app_path('Rules/ExistsExplicitTestRule.php'));
        $this->assertFileExists(app_path('Observers/ExplicitTestObserver.php'));
    }

    private function cleanupTestFiles(): void
    {
        $models = ['ConfigTestModel', 'ApiConfigTest', 'OverrideTest', 'ExplicitTest'];

        foreach ($models as $model) {
            // Remove model
            $modelPath = app_path("Models/{$model}.php");
            if (File::exists($modelPath)) {
                File::delete($modelPath);
            }

            // Remove controllers
            $controllerPath = app_path("Http/Controllers/{$model}Controller.php");
            if (File::exists($controllerPath)) {
                File::delete($controllerPath);
            }

            $apiControllerPath = app_path("Http/Controllers/Api/{$model}Controller.php");
            if (File::exists($apiControllerPath)) {
                File::delete($apiControllerPath);
            }

            // Remove requests
            $storeRequestPath = app_path("Http/Requests/Store{$model}Request.php");
            if (File::exists($storeRequestPath)) {
                File::delete($storeRequestPath);
            }

            $updateRequestPath = app_path("Http/Requests/Update{$model}Request.php");
            if (File::exists($updateRequestPath)) {
                File::delete($updateRequestPath);
            }

            // Remove resources
            $resourcePath = app_path("Http/Resources/{$model}Resource.php");
            if (File::exists($resourcePath)) {
                File::delete($resourcePath);
            }

            // Remove factories
            $factoryPath = database_path("factories/{$model}Factory.php");
            if (File::exists($factoryPath)) {
                File::delete($factoryPath);
            }

            // Remove seeders
            $seederPath = database_path("seeders/{$model}Seeder.php");
            if (File::exists($seederPath)) {
                File::delete($seederPath);
            }

            // Remove policies
            $policyPath = app_path("Policies/{$model}Policy.php");
            if (File::exists($policyPath)) {
                File::delete($policyPath);
            }

            // Remove actions
            $createActionPath = app_path("Actions/Create{$model}Action.php");
            if (File::exists($createActionPath)) {
                File::delete($createActionPath);
            }

            $updateActionPath = app_path("Actions/Update{$model}Action.php");
            if (File::exists($updateActionPath)) {
                File::delete($updateActionPath);
            }

            $deleteActionPath = app_path("Actions/Delete{$model}Action.php");
            if (File::exists($deleteActionPath)) {
                File::delete($deleteActionPath);
            }

            $getActionPath = app_path("Actions/Get{$model}Action.php");
            if (File::exists($getActionPath)) {
                File::delete($getActionPath);
            }

            // Remove services
            $servicePath = app_path("Services/{$model}Service.php");
            if (File::exists($servicePath)) {
                File::delete($servicePath);
            }

            // Remove rules
            $existsRulePath = app_path("Rules/Exists{$model}Rule.php");
            if (File::exists($existsRulePath)) {
                File::delete($existsRulePath);
            }

            $uniqueRulePath = app_path("Rules/Unique{$model}Rule.php");
            if (File::exists($uniqueRulePath)) {
                File::delete($uniqueRulePath);
            }

            // Remove observers
            $observerPath = app_path("Observers/{$model}Observer.php");
            if (File::exists($observerPath)) {
                File::delete($observerPath);
            }

            // Remove tests
            $featureTestPath = base_path("tests/Feature/{$model}Test.php");
            if (File::exists($featureTestPath)) {
                File::delete($featureTestPath);
            }

            $unitTestPath = base_path("tests/Unit/{$model}UnitTest.php");
            if (File::exists($unitTestPath)) {
                File::delete($unitTestPath);
            }

            // Remove views
            $viewsPath = resource_path('views/'.mb_strtolower(\Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($model))));
            if (File::isDirectory($viewsPath)) {
                File::deleteDirectory($viewsPath);
            }

            // Remove migrations
            $tableName = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($model));
            $migrations = File::glob(database_path("migrations/*_create_{$tableName}_table.php"));
            foreach ($migrations as $migration) {
                File::delete($migration);
            }
        }
    }
}
