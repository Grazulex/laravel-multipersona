<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Middleware;

use Closure;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPersonaFromRequest
{
    public function __construct(
        private PersonaManager $personaManager
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $parameter = 'persona_id'): Response
    {
        $personaId = $request->get($parameter);

        if ($personaId && $this->personaManager->canActivate($personaId)) {
            $this->personaManager->setActive($personaId);
        }

        return $next($request);
    }
}
