<?php

declare(strict_types=1);

namespace Grazulex\LaravelMultiPersona\Events;

use Grazulex\LaravelMultiPersona\Models\PersonaSwitchLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched once a persona switch has been written to the audit table.
 */
class PersonaSwitchLogged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PersonaSwitchLog $log,
        public readonly mixed $user = null,
    ) {}

    /**
     * Get the audit entry that was recorded
     */
    public function getLog(): PersonaSwitchLog
    {
        return $this->log;
    }

    /**
     * Get the user who switched personas
     */
    public function getUser(): mixed
    {
        return $this->user;
    }
}
