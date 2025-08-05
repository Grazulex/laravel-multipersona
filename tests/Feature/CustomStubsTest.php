<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class CustomStubsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any test files before each test
        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        // Clean up any test files after each test
        $this->cleanupTestFiles();

        parent::tearDown();
    }

    public function test_generator_uses_published_stubs_when_available(): void
    {
        // First, publish the stubs
        $this->artisan('vendor:publish', [
            '--tag' => 'turbomaker-stubs',
            '--force' => true,
        ])->assertExitCode(0);

        $stubsPath = resource_path('stubs/turbomaker');
        $this->assertDirectoryExists($stubsPath);

        // Modify the model stub to add a custom comment
        $modelStubPath = $stubsPath.'/model.stub';
        $this->assertFileExists($modelStubPath);

        $originalStub = File::get($modelStubPath);
        $customStub = str_replace(
            '<?php',
            "<?php\n\n// This is a custom stub modification for testing",
            $originalStub
        );
        File::put($modelStubPath, $customStub);

        // Generate a module to test if it uses the custom stub
        $this->artisan('turbo:make', [
            'name' => 'CustomStubTest',
            '--force' => true,
        ])->assertExitCode(0);

        // Check that the generated model contains the custom comment
        $modelPath = app_path('Models/CustomStubTest.php');
        $this->assertFileExists($modelPath);

        $modelContent = File::get($modelPath);
        $this->assertStringContainsString('This is a custom stub modification for testing', $modelContent);
    }

    public function test_generator_falls_back_to_package_stubs_when_published_not_available(): void
    {
        // Generate a module without published stubs
        $this->artisan('turbo:make', [
            'name' => 'FallbackTest',
            '--force' => true,
        ])->assertExitCode(0);

        // Check that the model was still generated (using package stubs)
        $modelPath = app_path('Models/FallbackTest.php');
        $this->assertFileExists($modelPath);

        $modelContent = File::get($modelPath);
        $this->assertStringContainsString('class FallbackTest extends Model', $modelContent);
        $this->assertStringNotContainsString('This is a custom stub modification for testing', $modelContent);
    }

    private function cleanupTestFiles(): void
    {
        $models = ['CustomStubTest', 'FallbackTest'];

        foreach ($models as $model) {
            // Remove model
            $modelPath = app_path("Models/{$model}.php");
            if (File::exists($modelPath)) {
                File::delete($modelPath);
            }

            // Remove controller
            $controllerPath = app_path("Http/Controllers/{$model}Controller.php");
            if (File::exists($controllerPath)) {
                File::delete($controllerPath);
            }

            // Remove API controller
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

            // Remove migrations
            $migrations = glob(database_path('migrations/*_create_'.mb_strtolower($model).'s_table.php'));
            foreach ($migrations as $migration) {
                File::delete($migration);
            }

            // Remove views directory
            $viewsPath = resource_path('views/'.mb_strtolower($model).'s');
            if (File::isDirectory($viewsPath)) {
                File::deleteDirectory($viewsPath);
            }

            // Remove tests
            $featureTestPath = base_path("tests/Feature/{$model}Test.php");
            if (File::exists($featureTestPath)) {
                File::delete($featureTestPath);
            }

            $unitTestPath = base_path("tests/Unit/{$model}Test.php");
            if (File::exists($unitTestPath)) {
                File::delete($unitTestPath);
            }
        }

        // Clean up published stubs
        $stubsPath = resource_path('stubs/turbomaker');
        if (File::isDirectory($stubsPath)) {
            File::deleteDirectory($stubsPath);
        }

        $stubsParentPath = resource_path('stubs');
        if (File::isDirectory($stubsParentPath) && count(File::files($stubsParentPath)) === 0) {
            File::deleteDirectory($stubsParentPath);
        }
    }
}
