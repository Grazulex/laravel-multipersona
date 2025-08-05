<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class LaravelMultiPersonaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/multipersona.php',
            'multipersona'
        );

        $this->app->singleton(PersonaManager::class);
        $this->app->singleton('multipersona', function (Container $app) {
            return $app[PersonaManager::class];
        });
        $this->app->singleton(PersonaInterface::class, function (Container $app) {
            return $app[PersonaManager::class];
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/multipersona.php' => config_path('multipersona.php'),
        ], 'multipersona-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'multipersona-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerMiddleware();
        $this->registerHelpers();
    }

    private function registerMiddleware(): void
    {
        if (config('multipersona.register_middleware', true)) {
            $router = $this->app['router'];

            foreach (config('multipersona.middleware_aliases', []) as $alias => $middleware) {
                $router->aliasMiddleware($alias, $middleware);
            }
        }
    }

    private function registerHelpers(): void
    {
        if (! function_exists('persona')) {
            require_once __DIR__.'/helpers.php';
        }
    }
}
