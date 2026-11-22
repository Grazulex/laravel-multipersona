<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Grazulex\LaravelMultiPersona\Traits\BelongsToPersona;
use Illuminate\Database\Eloquent\Model;

/**
 * Model filtered by the active persona with the configured column.
 */
class Document extends Model
{
    use BelongsToPersona;

    public $timestamps = false;

    protected $table = 'documents';

    protected $guarded = [];
}
