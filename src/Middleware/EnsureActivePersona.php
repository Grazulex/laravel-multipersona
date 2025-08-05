<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Middleware;

use Closure;
use Grazulex\LaravelMultiPersona\Services\PersonaManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivePersona
{
    public function __construct(
        private PersonaManager $personaManager
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $redirectTo = null): Response
    {
        if (! $this->personaManager->hasActive()) {
            if ($redirectTo !== null && $redirectTo !== '' && $redirectTo !== '0') {
                return redirect($redirectTo);
            }

            abort(403, 'No active persona');
        }

        return $next($request);
    }
}
