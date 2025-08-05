<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaActivated;
use Grazulex\LaravelMultiPersona\Events\PersonaDeactivated;
use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Grazulex\LaravelMultiPersona\Listeners\CachePersonaPermissions;
use Grazulex\LaravelMultiPersona\Listeners\LogPersonaSwitch;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ListenersTest extends TestCase
{
    private CachePersonaPermissions $cacheListener;
    private LogPersonaSwitch $logListener;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cacheListener = new CachePersonaPermissions();
        $this->logListener = new LogPersonaSwitch();
    }

    public function test_cache_persona_permissions_handles_persona_activated(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->with(
                'multipersona:permissions:1:1',
                \Mockery::on(function ($data) {
                    return is_array($data)
                        && isset($data['persona_id'])
                        && isset($data['persona_name'])
                        && isset($data['context'])
                        && isset($data['permissions'])
                        && isset($data['cached_at'])
                        && $data['persona_id'] === 1
                        && $data['permissions'] === ['admin', 'read', 'write'];
                }),
                3600
            );

        $user = new class extends Model {
            protected $fillable = ['id', 'name'];
            public function getKey() {
                return $this->id;
            }
        };
        $user->id = 1;

        /** @var Persona $persona */
        $persona = Persona::factory()->create([
            'id' => 1,
            'context' => ['permissions' => ['admin', 'read', 'write']],
        ]);

        $event = new PersonaActivated($persona, $user, ['method' => 'test']);

        $this->cacheListener->handle($event);
    }

    public function test_log_persona_switch_handles_persona_switched(): void
    {
        // Test using the actual getSummary method
        $user = new class extends Model {
            protected $fillable = ['id', 'name'];
            public function getKey() {
                return $this->id;
            }
        };
        $user->id = 1;

        /** @var Persona $currentPersona */
        $currentPersona = Persona::factory()->create([
            'id' => 2,
            'name' => 'Admin',
            'context' => ['role' => 'admin'],
        ]);

        /** @var Persona $previousPersona */
        $previousPersona = Persona::factory()->create([
            'id' => 1,
            'name' => 'User',
        ]);

        $event = new PersonaSwitched($currentPersona, $previousPersona, $user, ['method' => 'test']);

        // Mock Log to expect the actual data structure from getSummary
        Log::shouldReceive('info')
            ->once()
            ->with('Persona switched', \Mockery::on(function ($logData) {
                return isset($logData['user_id']) 
                    && isset($logData['new_persona'])
                    && isset($logData['previous_persona'])
                    && isset($logData['is_initial_activation'])
                    && isset($logData['timestamp']);
            }));

        $this->logListener->handle($event);
    }

    public function test_cache_listener_handles_null_user(): void
    {
        /** @var Persona $persona */
        $persona = Persona::factory()->create([
            'id' => 1,
            'context' => ['permissions' => ['read']],
        ]);

        $event = new PersonaActivated($persona, null, ['method' => 'test']);

        // Should not call Cache::put when user is null
        Cache::shouldReceive('put')->never();

        $this->cacheListener->handle($event);
    }

    public function test_cache_listener_handles_event_with_context(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->with(
                'multipersona:permissions:2:3',
                \Mockery::on(function ($data) {
                    return is_array($data)
                        && isset($data['persona_id'])
                        && isset($data['persona_name'])
                        && isset($data['context'])
                        && isset($data['permissions'])
                        && isset($data['role'])
                        && isset($data['cached_at'])
                        && $data['persona_id'] === 3
                        && $data['role'] === 'user'
                        && $data['permissions'] === ['read', 'write'];
                }),
                3600
            );

        $user = new class extends Model {
            protected $fillable = ['id', 'name'];
            public function getKey() {
                return $this->id;
            }
        };
        $user->id = 2;

        /** @var Persona $persona */
        $persona = Persona::factory()->create([
            'id' => 3,
            'context' => [
                'role' => 'user',
                'permissions' => ['read', 'write'],
                'department' => 'IT'
            ],
        ]);

        $event = new PersonaActivated($persona, $user, ['method' => 'test']);

        $this->cacheListener->handle($event);
    }

    public function test_log_listener_handles_initial_activation(): void
    {
        $user = new class extends Model {
            protected $fillable = ['id', 'name'];
            public function getKey() {
                return $this->id;
            }
        };
        $user->id = 1;

        /** @var Persona $currentPersona */
        $currentPersona = Persona::factory()->create([
            'id' => 1,
            'name' => 'First Persona',
            'context' => ['role' => 'user'],
        ]);

        // When previous persona is null, it's an initial activation
        $event = new PersonaSwitched($currentPersona, null, $user, ['method' => 'test']);

        Log::shouldReceive('info')
            ->once()
            ->with('Persona switched', \Mockery::on(function ($logData) {
                return $logData['is_initial_activation'] === true;
            }));

        $this->logListener->handle($event);
    }
}
