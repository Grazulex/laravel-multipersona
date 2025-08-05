<?php

declare(strict_types=1);

namespace Tests\Unit;

use Grazulex\LaravelTurbomaker\Console\Commands\TurboMakeCommand;
use Tests\TestCase;

final class CommandRegistrationTest extends TestCase
{
    public function test_turbo_make_command_is_registered(): void
    {
        // Test via artisan command availability
        $this->artisan('list')
            ->assertExitCode(0);

        // Test that our service provider registered the command
        $this->assertTrue($this->app->bound(TurboMakeCommand::class));

        // Test that the command can be resolved
        $command = $this->app->make(TurboMakeCommand::class);
        $this->assertInstanceOf(TurboMakeCommand::class, $command);
    }

    public function test_turbo_make_command_signature(): void
    {
        $command = $this->app->make(TurboMakeCommand::class);

        $this->assertEquals('turbo:make', $command->getName());
        $this->assertStringContainsString('Generate a complete Laravel module', $command->getDescription());
    }

    public function test_turbo_make_command_has_expected_options(): void
    {
        $command = $this->app->make(TurboMakeCommand::class);
        $definition = $command->getDefinition();

        // Check that expected options exist
        $this->assertTrue($definition->hasOption('api'));
        $this->assertTrue($definition->hasOption('views'));
        $this->assertTrue($definition->hasOption('policies'));
        $this->assertTrue($definition->hasOption('factory'));
        $this->assertTrue($definition->hasOption('seeder'));
        $this->assertTrue($definition->hasOption('tests'));
        $this->assertTrue($definition->hasOption('actions'));
        $this->assertTrue($definition->hasOption('services'));
        $this->assertTrue($definition->hasOption('rules'));
        $this->assertTrue($definition->hasOption('observers'));
        $this->assertTrue($definition->hasOption('belongs-to'));
        $this->assertTrue($definition->hasOption('has-many'));
        $this->assertTrue($definition->hasOption('has-one'));
        $this->assertTrue($definition->hasOption('force'));
    }

    public function test_turbo_make_command_has_name_argument(): void
    {
        $command = $this->app->make(TurboMakeCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('name'));

        $nameArgument = $definition->getArgument('name');
        $this->assertTrue($nameArgument->isRequired());
        $this->assertEquals('The name of the module to generate', $nameArgument->getDescription());
    }
}
