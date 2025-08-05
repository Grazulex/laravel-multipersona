<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Grazulex\LaravelMultiPersona\Middleware\EnsureActivePersona;
use Grazulex\LaravelMultiPersona\Models\Persona;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class EnsureActivePersonaTest extends TestCase
{
    private EnsureActivePersona $middleware;

    private PersonaManager $personaManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personaManager = app(PersonaManager::class);
        $this->middleware = new EnsureActivePersona($this->personaManager);
    }

    public function test_allows_request_when_persona_is_active(): void
    {
        // Create a user and persona
        $user = $this->createUser();
        $persona = Persona::create([
            'user_id' => $user->id,
            'name' => 'Test Persona',
            'context' => ['role' => 'admin'],
            'is_active' => true,
        ]);

        // Set active persona
        $this->personaManager->setActive($persona);

        $request = Request::create('/test', 'GET');
        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_redirects_when_no_active_persona(): void
    {
        // Clear any active persona
        $this->personaManager->clear();

        $request = Request::create('/test', 'GET');
        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringStartsWith('http://localhost/personas/select', $response->headers->get('Location') ?? '');
    }

    public function test_handles_custom_redirect_url(): void
    {
        $customUrl = '/custom/persona/select';
        $middleware = new EnsureActivePersona($this->personaManager, $customUrl);

        // Clear any active persona
        $this->personaManager->clear();

        $request = Request::create('/test', 'GET');
        $next = fn ($request) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringStartsWith('http://localhost'.$customUrl, $response->headers->get('Location') ?? '');
    }

    public function test_handles_ajax_requests(): void
    {
        // Clear any active persona
        $this->personaManager->clear();

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringStartsWith('{"error":"No active persona selected"', $response->getContent());
    }

    public function test_handles_json_requests(): void
    {
        // Clear any active persona
        $this->personaManager->clear();

        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        $next = fn ($request) => new Response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('No active persona selected', $content['error']);
    }

    private function createUser(): object
    {
        // Create a simple User-like model for testing
        return new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';

            protected $fillable = ['name', 'email'];

            public $timestamps = false;

            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
                $this->id = 1;
                $this->name = 'Test User';
                $this->email = 'test@example.com';
            }
        };
    }
}
