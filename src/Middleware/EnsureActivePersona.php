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
        private PersonaManager $personaManager,
        private readonly string $redirectUrl = '/personas/select'
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $redirectTo = null): Response
    {
        if (! $this->personaManager->hasActive()) {
            return $this->handleNoActivePersona($request, $redirectTo);
        }

        return $next($request);
    }

    /**
     * Handle the case when no active persona is set
     */
    private function handleNoActivePersona(Request $request, ?string $redirectTo = null): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'error' => 'No active persona selected',
                'message' => 'Please select a persona to continue',
            ], 403);
        }

        $redirectUrl = $redirectTo ?? $this->redirectUrl;

        return redirect($redirectUrl)
            ->with('error', 'Please select a persona to continue');
    }
}
