<?php

declare(strict_types=1);

use function Pest\Laravel\artisan;

describe('TurboMaker Commands', function () {
    it('can show turbo:api help', function () {
        artisan('turbo:api --help')
            ->assertExitCode(0);
    });

    it('can show turbo:make help', function () {
        artisan('turbo:make --help')
            ->assertExitCode(0);
    });

    it('can show turbo:schema help', function () {
        artisan('turbo:schema --help')
            ->assertExitCode(0);
    });
});
