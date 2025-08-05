<?php

declare(strict_types=1);

if (! function_exists('persona')) {
    /**
     * Get the current active persona
     */
    function persona(): ?Grazulex\LaravelMultiPersona\Contracts\PersonaInterface
    {
        return app('multipersona')->current();
    }
}

if (! function_exists('personas')) {
    /**
     * Get all personas for a user
     */
    function personas($user = null): Illuminate\Support\Collection
    {
        $user = $user ?: Illuminate\Support\Facades\Auth::user();

        return $user->personas ?? collect();
    }
}
