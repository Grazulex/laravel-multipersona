<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Listeners;

use Grazulex\LaravelMultiPersona\Events\PersonaSwitched;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogPersonaSwitch implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(PersonaSwitched $event): void
    {
        $summary = $event->getSummary();

        Log::info('Persona switched', [
            'user_id' => $summary['user']['id'] ?? null,
            'user_type' => $summary['user']['type'] ?? null,
            'previous_persona' => $summary['previous_persona']['name'] ?? null,
            'new_persona' => $summary['new_persona']['name'],
            'new_persona_role' => $summary['new_persona']['context']['role'] ?? null,
            'is_initial_activation' => $summary['is_initial_activation'],
            'timestamp' => $summary['timestamp'],
        ]);
    }
}
