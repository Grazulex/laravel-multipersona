<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Middleware;

use Closure;
use Exception;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPersonaFromRequest
{
    public function __construct(
        private PersonaManager $personaManager,
        private readonly string $parameterName = 'persona_id'
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        $paramName = $parameter ?? $this->parameterName;

        // Try to get persona ID from request parameter or header
        $personaId = $request->get($paramName)
                  ?? $request->header('X-Persona-ID');

        if ($personaId && $this->isValidPersonaId($personaId)) {
            try {
                $this->personaManager->setActive($personaId);
            } catch (Exception $e) {
                // Silently ignore invalid persona IDs
            }
        }

        return $next($request);
    }

    /**
     * Check if the persona ID is valid
     */
    private function isValidPersonaId(mixed $personaId): bool
    {
        return is_numeric($personaId) || (is_string($personaId) && ctype_digit($personaId));
    }
}
