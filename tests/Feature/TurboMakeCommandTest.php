<?php

declare(strict_types=1);

namespace Tests\Feature;

use Grazulex\LaravelTurbomaker\Console\Commands\TurboMakeCommand;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class TurboMakeCommandTest extends TestCase
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

    public function test_turbo_make_command_exists(): void
    {
        $this->artisan('list')
            ->assertExitCode(0);

        // Alternative way to check command registration
        $this->assertTrue($this->app->bound(TurboMakeCommand::class));
    }

    public function test_turbo_make_generates_basic_module(): void
    {
        $this->artisan('turbo:make', ['name' => 'TestModel', '--force' => true])
            ->assertExitCode(0);

        // Check that model was created
        $this->assertFileExists(app_path('Models/TestModel.php'));

        // Check that migration was created
        $migrations = File::glob(database_path('migrations/*_create_test_models_table.php'));
        $this->assertNotEmpty($migrations, 'Migration file should be created');

        // Check that controllers were created
        $this->assertFileExists(app_path('Http/Controllers/TestModelController.php'));
        $this->assertFileExists(app_path('Http/Controllers/Api/TestModelController.php'));

        // Check that requests were created
        $this->assertFileExists(app_path('Http/Requests/StoreTestModelRequest.php'));
        $this->assertFileExists(app_path('Http/Requests/UpdateTestModelRequest.php'));

        // Check that resource was created
        $this->assertFileExists(app_path('Http/Resources/TestModelResource.php'));
    }

    public function test_turbo_make_with_api_only_option(): void
    {
        $this->artisan('turbo:make', ['name' => 'ApiModel', '--api' => true, '--force' => true])
            ->assertExitCode(0);

        // Should create API controller
        $this->assertFileExists(app_path('Http/Controllers/Api/ApiModelController.php'));

        // Should NOT create web controller
        $this->assertFileDoesNotExist(app_path('Http/Controllers/ApiModelController.php'));
    }

    public function test_turbo_make_with_relationships(): void
    {
        $this->artisan('turbo:make', [
            'name' => 'Post',
            '--belongs-to' => ['User'],
            '--has-many' => ['Comment'],
            '--force' => true,
        ])->assertExitCode(0);

        // Check that model contains relationships
        $modelContent = File::get(app_path('Models/Post.php'));
        $this->assertStringContainsString('public function user(): BelongsTo', $modelContent);
        $this->assertStringContainsString('public function comments(): HasMany', $modelContent);

        // Check that migration contains foreign key
        $migrations = File::glob(database_path('migrations/*_create_posts_table.php'));
        $this->assertNotEmpty($migrations);
        $migrationContent = File::get($migrations[0]);
        $this->assertStringContainsString('user_id', $migrationContent);
    }

    public function test_turbo_make_with_factory_option(): void
    {
        $this->artisan('turbo:make', ['name' => 'Product', '--factory' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists(database_path('factories/ProductFactory.php'));
    }

    public function test_turbo_make_with_seeder_option(): void
    {
        $this->artisan('turbo:make', ['name' => 'Category', '--seeder' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists(database_path('seeders/CategorySeeder.php'));
    }

    public function test_turbo_make_with_policies_option(): void
    {
        $this->artisan('turbo:make', ['name' => 'Article', '--policies' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Policies/ArticlePolicy.php'));
    }

    public function test_turbo_make_generates_tests(): void
    {
        $this->artisan('turbo:make', ['name' => 'TestEntity', '--tests' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists(base_path('tests/Feature/TestEntityTest.php'));
        $this->assertFileExists(base_path('tests/Unit/TestEntityUnitTest.php'));
    }

    public function test_generated_model_has_correct_structure(): void
    {
        $this->artisan('turbo:make', ['name' => 'Example', '--force' => true])
            ->assertExitCode(0);

        $modelContent = File::get(app_path('Models/Example.php'));

        // Check namespace
        $this->assertStringContainsString('namespace App\Models;', $modelContent);

        // Check class declaration
        $this->assertStringContainsString('final class Example extends Model', $modelContent);

        // Check HasFactory trait
        $this->assertStringContainsString('use HasFactory;', $modelContent);

        // Check fillable
        $this->assertStringContainsString('protected $fillable', $modelContent);
    }

    public function test_turbo_make_with_rules_only(): void
    {
        $this->artisan('turbo:make RuleModel --rules --force')
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Rules/ExistsRuleModelRule.php'));
        $this->assertFileExists(app_path('Rules/UniqueRuleModelRule.php'));
    }

    public function test_turbo_make_with_observers_only(): void
    {
        $this->artisan('turbo:make ObserverModel --observers --force')
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Observers/ObserverModelObserver.php'));
    }

    public function test_generated_rule_has_correct_structure(): void
    {
        $this->artisan('turbo:make RuleTest --rules --force')
            ->assertExitCode(0);

        $ruleContent = File::get(app_path('Rules/ExistsRuleTestRule.php'));

        // Check namespace
        $this->assertStringContainsString('namespace App\Rules;', $ruleContent);

        // Check imports
        $this->assertStringContainsString('use App\Models\RuleTest;', $ruleContent);
        $this->assertStringContainsString('use Illuminate\Contracts\Validation\ValidationRule;', $ruleContent);

        // Check class name
        $this->assertStringContainsString('final class ExistsRuleTestRule implements ValidationRule', $ruleContent);

        // Check method
        $this->assertStringContainsString('public function validate(string $attribute, mixed $value, Closure $fail): void', $ruleContent);

        // Check logic
        $this->assertStringContainsString('RuleTest::where(\'id\', $value)->exists()', $ruleContent);
    }

    public function test_generated_observer_has_correct_structure(): void
    {
        $this->artisan('turbo:make ObserverTest --observers --force')
            ->assertExitCode(0);

        $observerContent = File::get(app_path('Observers/ObserverTestObserver.php'));

        // Check namespace
        $this->assertStringContainsString('namespace App\Observers;', $observerContent);

        // Check imports
        $this->assertStringContainsString('use App\Models\ObserverTest;', $observerContent);

        // Check class name
        $this->assertStringContainsString('final class ObserverTestObserver', $observerContent);

        // Check key methods
        $this->assertStringContainsString('public function creating(ObserverTest $observerTest): void', $observerContent);
        $this->assertStringContainsString('public function created(ObserverTest $observerTest): void', $observerContent);
        $this->assertStringContainsString('public function updating(ObserverTest $observerTest): void', $observerContent);
        $this->assertStringContainsString('public function updated(ObserverTest $observerTest): void', $observerContent);
        $this->assertStringContainsString('public function deleting(ObserverTest $observerTest): void', $observerContent);
        $this->assertStringContainsString('public function deleted(ObserverTest $observerTest): void', $observerContent);
    }

    public function test_turbo_make_with_all_options(): void
    {
        $this->artisan('turbo:make CompleteModel --api --views --policies --factory --seeder --tests --actions --services --rules --observers --belongs-to=User --has-many=Posts --has-one=Profile --force')
            ->assertExitCode(0);

        // Check Actions are created
        $this->assertFileExists(app_path('Actions/CreateCompleteModelAction.php'));
        $this->assertFileExists(app_path('Actions/UpdateCompleteModelAction.php'));
        $this->assertFileExists(app_path('Actions/DeleteCompleteModelAction.php'));
        $this->assertFileExists(app_path('Actions/GetCompleteModelAction.php'));

        // Check Service is created
        $this->assertFileExists(app_path('Services/CompleteModelService.php'));

        // Check Rules are created
        $this->assertFileExists(app_path('Rules/ExistsCompleteModelRule.php'));
        $this->assertFileExists(app_path('Rules/UniqueCompleteModelRule.php'));

        // Check Observer is created
        $this->assertFileExists(app_path('Observers/CompleteModelObserver.php'));

        // Check Policy is created
        $this->assertFileExists(app_path('Policies/CompleteModelPolicy.php'));
    }

    public function test_turbo_make_with_actions_only(): void
    {
        $this->artisan('turbo:make ActionModel --actions --force')
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Actions/CreateActionModelAction.php'));
        $this->assertFileExists(app_path('Actions/UpdateActionModelAction.php'));
        $this->assertFileExists(app_path('Actions/DeleteActionModelAction.php'));
        $this->assertFileExists(app_path('Actions/GetActionModelAction.php'));
    }

    public function test_turbo_make_with_services_only(): void
    {
        $this->artisan('turbo:make ServiceModel --services --force')
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Services/ServiceModelService.php'));
    }

    public function test_generated_action_has_correct_structure(): void
    {
        $this->artisan('turbo:make ActionTest --actions --belongs-to=User --force')
            ->assertExitCode(0);

        $actionContent = File::get(app_path('Actions/CreateActionTestAction.php'));

        // Check namespace
        $this->assertStringContainsString('namespace App\Actions;', $actionContent);

        // Check imports
        $this->assertStringContainsString('use App\Models\ActionTest;', $actionContent);
        $this->assertStringContainsString('use App\Http\Requests\StoreActionTestRequest;', $actionContent);

        // Check class name
        $this->assertStringContainsString('final class CreateActionTestAction', $actionContent);

        // Check method
        $this->assertStringContainsString('public function execute(StoreActionTestRequest $request): ActionTest', $actionContent);

        // Check relationship field
        $this->assertStringContainsString('user_id', $actionContent);
    }

    public function test_generated_service_has_correct_structure(): void
    {
        $this->artisan('turbo:make ServiceTest --services --belongs-to=Category --force')
            ->assertExitCode(0);

        $serviceContent = File::get(app_path('Services/ServiceTestService.php'));

        // Check namespace
        $this->assertStringContainsString('namespace App\Services;', $serviceContent);

        // Check imports
        $this->assertStringContainsString('use App\Models\ServiceTest;', $serviceContent);
        $this->assertStringContainsString('use App\Http\Requests\StoreServiceTestRequest;', $serviceContent);
        $this->assertStringContainsString('use App\Http\Requests\UpdateServiceTestRequest;', $serviceContent);

        // Check class name
        $this->assertStringContainsString('final class ServiceTestService', $serviceContent);

        // Check methods
        $this->assertStringContainsString('public function getAll(): Collection', $serviceContent);
        $this->assertStringContainsString('public function create(StoreServiceTestRequest $request): ServiceTest', $serviceContent);
        $this->assertStringContainsString('public function update(ServiceTest $serviceTest, UpdateServiceTestRequest $request): ServiceTest', $serviceContent);
        $this->assertStringContainsString('public function delete(ServiceTest $serviceTest): bool', $serviceContent);

        // Check relationship field
        $this->assertStringContainsString('category_id', $serviceContent);
    }

    private function cleanupTestFiles(): void
    {
        $testModels = [
            'TestModel', 'ApiModel', 'Post', 'Product', 'Category',
            'Article', 'TestEntity', 'Example', 'Sample',
        ];

        foreach ($testModels as $model) {
            // Remove models
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

            // Remove tests
            $featureTestPath = base_path("tests/Feature/{$model}Test.php");
            if (File::exists($featureTestPath)) {
                File::delete($featureTestPath);
            }

            $unitTestPath = base_path("tests/Unit/{$model}UnitTest.php");
            if (File::exists($unitTestPath)) {
                File::delete($unitTestPath);
            }

            // Remove migrations (more complex due to timestamp)
            $tableName = mb_strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $model));
            $tableName = \Illuminate\Support\Str::plural($tableName);
            $migrations = File::glob(database_path("migrations/*_create_{$tableName}_table.php"));
            foreach ($migrations as $migration) {
                File::delete($migration);
            }
        }

        // Clean up directories if they're empty
        $this->cleanupEmptyDirectories();
    }

    private function cleanupEmptyDirectories(): void
    {
        $directories = [
            app_path('Http/Controllers/Api'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
            app_path('Policies'),
            database_path('factories'),
            database_path('seeders'),
        ];

        foreach ($directories as $dir) {
            if (File::isDirectory($dir) && count(File::files($dir)) === 0) {
                // Don't delete the directory, just ensure it's clean
                continue;
            }
        }
    }
}
