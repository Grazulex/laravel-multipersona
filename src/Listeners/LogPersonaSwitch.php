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

        $context = [
            'user_id' => $summary['user']['id'] ?? null,
            'user_type' => $summary['user']['type'] ?? null,
            'previous_persona_id' => $summary['previous_persona']['id'] ?? null,
            'previous_persona' => $summary['previous_persona']['name'] ?? null,
            'new_persona_id' => $summary['new_persona']['id'],
            'new_persona' => $summary['new_persona']['name'],
            'new_persona_role' => $summary['new_persona']['context']['role'] ?? null,
            'is_initial_activation' => $summary['is_initial_activation'],
            'timestamp' => $summary['timestamp'],
        ];

        // Send the entry to a dedicated audit channel when one is configured
        $channel = config('multipersona.audit.log_channel');

        if (is_string($channel) && $channel !== '') {
            Log::channel($channel)->info('Persona switched', $context);

            return;
        }

        Log::info('Persona switched', $context);
    }
}
