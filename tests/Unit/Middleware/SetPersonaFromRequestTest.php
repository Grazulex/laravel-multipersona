<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Grazulex\LaravelMultiPersona\Middleware\SetPersonaFromRequest;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SetPersonaFromRequestTest extends TestCase
{
    private SetPersonaFromRequest $middleware;

    private PersonaManager $personaManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personaManager = app(PersonaManager::class);
        $this->middleware = new SetPersonaFromRequest($this->personaManager);
    }

    public function test_sets_persona_from_request_parameter(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->id,
            'name' => 'Test Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        $request = Request::create('/test', 'GET', ['persona_id' => $persona->id]);
        $request->setUserResolver(fn () => $user);
        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($persona->id, $this->personaManager->current()?->getId());
    }

    public function test_sets_persona_from_header(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->id,
            'name' => 'Test Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Persona-ID', (string) $persona->id);
        $request->setUserResolver(fn () => $user);

        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($persona->id, $this->personaManager->current()?->getId());
    }

    public function test_ignores_invalid_persona_id(): void
    {
        $request = Request::create('/test', 'GET', ['persona_id' => 999]);
        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($this->personaManager->current());
    }

    public function test_continues_without_persona_parameter(): void
    {
        $request = Request::create('/test', 'GET');
        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_uses_custom_parameter_name(): void
    {
        $middleware = new SetPersonaFromRequest($this->personaManager, 'custom_persona');

        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->id,
            'name' => 'Test Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        $request = Request::create('/test', 'GET', ['custom_persona' => $persona->id]);
        $request->setUserResolver(fn () => $user);
        $next = fn ($request) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($persona->id, $this->personaManager->current()?->getId());
    }

    public function test_refuses_persona_owned_by_another_user(): void
    {
        $owner = $this->createUser(1);
        $attacker = $this->createUser(2);
        $persona = Persona::create([
            'user_id' => $owner->id,
            'name' => 'Owner Persona',
            'context' => ['role' => 'admin'],
            'is_active' => false,
        ]);

        $request = Request::create('/test', 'GET', ['persona_id' => $persona->id]);
        $request->setUserResolver(fn () => $attacker);
        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($this->personaManager->current());
    }

    public function test_refuses_persona_when_unauthenticated(): void
    {
        $owner = $this->createUser(1);
        $persona = Persona::create([
            'user_id' => $owner->id,
            'name' => 'Owner Persona',
            'context' => ['role' => 'admin'],
            'is_active' => false,
        ]);

        $request = Request::create('/test', 'GET', ['persona_id' => $persona->id]);
        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($this->personaManager->current());
    }

    private function createUser(int $id = 1): object
    {
        // Minimal User-like model that uses HasPersonas, as in a real application
        return new class($id) extends \Illuminate\Database\Eloquent\Model
        {
            use \Grazulex\LaravelMultiPersona\Traits\HasPersonas;

            protected $table = 'users';

            protected $fillable = ['name', 'email'];

            public $timestamps = false;

            public function __construct(int $id = 1)
            {
                parent::__construct();
                $this->id = $id;
                $this->name = 'Test User '.$id;
                $this->email = 'test'.$id.'@example.com';
            }
        };
    }
}
