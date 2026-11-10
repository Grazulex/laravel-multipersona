<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona;

use Grazulex\LaravelMultiPersona\Contracts\PersonaInterface;
use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Listeners\CachePersonaPermissions;
use Grazulex\LaravelMultiPersona\Listeners\ForgetPersonaPermissions;
use Grazulex\LaravelMultiPersona\Listeners\LogPersonaSwitch;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Event;
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
        $this->registerListeners();
        $this->registerHelpers();
    }

    private function registerListeners(): void
    {
        if (! config('multipersona.register_listeners', true)) {
            return;
        }

        Event::listen(PersonaActivated::class, CachePersonaPermissions::class);
        Event::listen(PersonaSwitched::class, LogPersonaSwitch::class);
        Event::listen(PersonaSwitched::class, ForgetPersonaPermissions::class);
        Event::listen(PersonaDeactivated::class, ForgetPersonaPermissions::class);

        // A modified or deleted persona must not keep serving cached permissions
        $forget = static function (Persona $persona): void {
            $userId = $persona->getAttribute('user_id');

            if (is_int($userId) || is_string($userId)) {
                CachePersonaPermissions::forget($userId, $persona);
            }
        };

        Persona::updated($forget);
        Persona::deleted($forget);
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
