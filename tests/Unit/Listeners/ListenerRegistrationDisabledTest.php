<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Listeners\CachePersonaPermissions;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ListenerRegistrationDisabledTest extends TestCase
{
    public function test_listeners_are_not_registered_when_disabled(): void
    {
        $this->assertFalse(Event::hasListeners(PersonaActivated::class));
        $this->assertFalse(Event::hasListeners(PersonaSwitched::class));
        $this->assertFalse(Event::hasListeners(PersonaDeactivated::class));
    }

    public function test_updating_a_persona_keeps_the_cache_when_disabled(): void
    {
        $persona = Persona::create([
            'user_id' => 1,
            'name' => 'Editor',
            'context' => [],
            'is_active' => false,
        ]);
        $key = CachePersonaPermissions::cacheKey(1, $persona);
        Cache::put($key, ['permissions' => []], 3600);

        $persona->update(['name' => 'Renamed']);

        $this->assertTrue(Cache::has($key));
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('multipersona.register_listeners', false);
    }
}
