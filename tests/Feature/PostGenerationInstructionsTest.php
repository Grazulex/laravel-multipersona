<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PostGenerationInstructionsTest extends TestCase
{
    #[Test]
    public function turbo_make_shows_observer_registration_instructions(): void
    {
        $this->artisan('turbo:make', [
            'name' => 'Product',
            '--observers' => true,
        ])
            ->expectsOutputToContain('Register the ProductObserver in your AppServiceProvider boot() method')
            ->expectsOutputToContain('\\App\\Models\\Product::observe(\\App\\Observers\\ProductObserver::class);')
            ->expectsOutputToContain('⚠️  IMPORTANT: Don\'t forget to register your Observer in AppServiceProvider!')
            ->assertExitCode(0);
    }

    #[Test]
    public function turbo_make_shows_policy_registration_instructions(): void
    {
        $this->artisan('turbo:make', [
            'name' => 'Product',
            '--policies' => true,
        ])
            ->expectsOutputToContain('Register the ProductPolicy in your AuthServiceProvider')
            ->expectsOutputToContain('\\App\\Models\\Product::class => \\App\\Policies\\ProductPolicy::class,')
            ->expectsOutputToContain('⚠️  IMPORTANT: Don\'t forget to register your Policy in AuthServiceProvider!')
            ->assertExitCode(0);
    }

    #[Test]
    public function turbo_make_shows_seeder_registration_instructions(): void
    {
        $this->artisan('turbo:make', [
            'name' => 'Product',
            '--seeder' => true,
        ])
            ->expectsOutputToContain('Add ProductSeeder to your DatabaseSeeder')
            ->expectsOutputToContain('$this->call(ProductSeeder::class);')
            ->assertExitCode(0);
    }

    #[Test]
    public function turbo_api_shows_api_specific_instructions(): void
    {
        $this->artisan('turbo:api', [
            'name' => 'Product',
            '--observers' => true,
        ])
            ->expectsOutputToContain('Add API routes to your routes/api.php')
            ->expectsOutputToContain('Route::apiResource(')
            ->expectsOutputToContain('Check your API routes: php artisan route:list --path=api')
            ->expectsOutputToContain('⚠️  IMPORTANT: Don\'t forget to register your Observer in AppServiceProvider!')
            ->assertExitCode(0);
    }
}
