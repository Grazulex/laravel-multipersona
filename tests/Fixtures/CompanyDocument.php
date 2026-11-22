<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Grazulex\LaravelMultiPersona\Traits\BelongsToPersona;
use Illuminate\Database\Eloquent\Model;

/**
 * Model filtered by the `company_id` of the active persona context.
 */
class CompanyDocument extends Model
{
    use BelongsToPersona;

    public $timestamps = false;

    protected $table = 'documents';

    protected $guarded = [];

    public function getPersonaColumn(): string
    {
        return 'company_id';
    }

    public function getPersonaContextKey(): ?string
    {
        return 'company_id';
    }
}
