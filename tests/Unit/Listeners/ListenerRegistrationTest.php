<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Listeners\CachePersonaPermissions;
use Grazulex\LaravelMultiPersona\Listeners\ForgetPersonaPermissions;
use Grazulex\LaravelMultiPersona\Listeners\LogPersonaSwitch;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use stdClass;
use Tests\TestCase;

class ListenerRegistrationTest extends TestCase
{
    public function test_bundled_listeners_are_registered(): void
    {
        $listeners = Event::getRawListeners();

        $this->assertContains(CachePersonaPermissions::class, $listeners[PersonaActivated::class] ?? []);
        $this->assertContains(LogPersonaSwitch::class, $listeners[PersonaSwitched::class] ?? []);
        $this->assertContains(ForgetPersonaPermissions::class, $listeners[PersonaSwitched::class] ?? []);
        $this->assertContains(ForgetPersonaPermissions::class, $listeners[PersonaDeactivated::class] ?? []);
    }

    public function test_cache_key_helpers(): void
    {
        $persona = $this->createPersona(7, 'Admin', ['permissions' => ['read']]);
        $user = new stdClass;
        $user->id = 7;

        $expected = 'multipersona:permissions:7:'.$persona->id;

        $this->assertSame($expected, CachePersonaPermissions::cacheKey($user, $persona));
        $this->assertSame($expected, CachePersonaPermissions::cacheKey(7, $persona->id));
        $this->assertSame($expected, CachePersonaPermissions::cacheKey((new Authenticatable)->forceFill(['id' => 7]), $persona));
    }

    public function test_dispatched_activation_caches_and_deactivation_forgets(): void
    {
        $persona = $this->createPersona(1, 'Admin', ['role' => 'admin', 'permissions' => ['read', 'write']]);
        $user = new stdClass;
        $user->id = 1;

        PersonaActivated::dispatch($persona, $user, ['method' => 'test']);

        $cached = CachePersonaPermissions::cached($user, $persona);
        $this->assertIsArray($cached);
        $this->assertSame(['read', 'write'], $cached['permissions']);
        $this->assertSame('admin', $cached['role']);

        PersonaDeactivated::dispatch($persona, $user, ['method' => 'test']);

        $this->assertNull(CachePersonaPermissions::cached($user, $persona));
    }

    public function test_switch_forgets_the_previous_persona_only(): void
    {
        $previous = $this->createPersona(1, 'User');
        $current = $this->createPersona(1, 'Admin');

        Cache::put(CachePersonaPermissions::cacheKey(1, $previous), ['permissions' => []], 3600);
        Cache::put(CachePersonaPermissions::cacheKey(1, $current), ['permissions' => []], 3600);

        (new ForgetPersonaPermissions)->handle(new PersonaSwitched($current, $previous, 1));

        $this->assertFalse(Cache::has(CachePersonaPermissions::cacheKey(1, $previous)));
        $this->assertTrue(Cache::has(CachePersonaPermissions::cacheKey(1, $current)));
    }

    public function test_switch_to_the_same_persona_keeps_its_cache(): void
    {
        $persona = $this->createPersona(1, 'Admin');
        Cache::put(CachePersonaPermissions::cacheKey(1, $persona), ['permissions' => []], 3600);

        (new ForgetPersonaPermissions)->handle(new PersonaSwitched($persona, $persona, 1));

        $this->assertTrue(Cache::has(CachePersonaPermissions::cacheKey(1, $persona)));
    }

    public function test_forget_listener_ignores_events_without_user(): void
    {
        $persona = $this->createPersona(1, 'Admin');
        Cache::put(CachePersonaPermissions::cacheKey(1, $persona), ['permissions' => []], 3600);

        $listener = new ForgetPersonaPermissions;
        $listener->handle(new PersonaDeactivated($persona));
        $listener->handle(new PersonaSwitched($this->createPersona(1, 'Other'), $persona));

        $this->assertTrue(Cache::has(CachePersonaPermissions::cacheKey(1, $persona)));
    }

    public function test_updating_a_persona_forgets_its_cache(): void
    {
        $persona = $this->createPersona(3, 'Editor', ['permissions' => ['read']]);
        Cache::put(CachePersonaPermissions::cacheKey(3, $persona), ['permissions' => ['read']], 3600);

        $persona->update(['context' => ['permissions' => ['read', 'write']]]);

        $this->assertFalse(Cache::has(CachePersonaPermissions::cacheKey(3, $persona)));
    }

    public function test_deleting_a_persona_forgets_its_cache(): void
    {
        $persona = $this->createPersona(3, 'Editor');
        $key = CachePersonaPermissions::cacheKey(3, $persona);
        Cache::put($key, ['permissions' => []], 3600);

        $persona->delete();

        $this->assertFalse(Cache::has($key));
    }

    public function test_persona_manager_keeps_the_cache_in_sync(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('users')->insert(['id' => 1]);

        $user = (new Authenticatable)->forceFill(['id' => 1]);
        $user->exists = true;
        $this->actingAs($user);

        $first = $this->createPersona(1, 'First', ['permissions' => ['read']]);
        $second = $this->createPersona(1, 'Second', ['permissions' => ['write']]);

        $manager = app(PersonaManager::class);

        $manager->setActive($first);
        $this->assertSame(['read'], CachePersonaPermissions::cached($user, $first)['permissions'] ?? null);

        $manager->setActive($second);
        $this->assertNull(CachePersonaPermissions::cached($user, $first));
        $this->assertSame(['write'], CachePersonaPermissions::cached($user, $second)['permissions'] ?? null);

        $manager->clear();
        $this->assertNull(CachePersonaPermissions::cached($user, $second));
    }

    private function createPersona(int $userId, string $name, array $context = []): Persona
    {
        return Persona::create([
            'user_id' => $userId,
            'name' => $name,
            'context' => $context,
            'is_active' => false,
        ]);
    }
}
