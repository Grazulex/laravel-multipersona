<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Grazulex\LaravelMultiPersona\Traits\HasPersonas;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Authenticatable user owning personas.
 */
class PersonaUser extends Authenticatable
{
    use HasPersonas;

    public $timestamps = false;

    protected $table = 'users';

    protected $guarded = [];
}
