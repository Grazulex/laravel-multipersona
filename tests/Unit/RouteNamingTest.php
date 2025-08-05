<?php

declare(strict_types=1);

use Illuminate\Support\Str;

it('verifies routes use plural kebab case naming', function () {
    $stubsDir = __DIR__.'/../../stubs';

    if (! is_dir($stubsDir)) {
        $this->markTestSkipped('Stubs directory not found');

        return;
    }

    $routeFiles = [
        'controller.stub',
        'test.feature.stub',
    ];

    foreach ($routeFiles as $stubFile) {
        $stubPath = "{$stubsDir}/{$stubFile}";

        if (file_exists($stubPath)) {
            $content = file_get_contents($stubPath);

            // Check that routes use plural_kebab for route() calls
            if (str_contains($content, 'route(')) {
                expect($content)->toContain('{{ plural_kebab }}');
            }
        }
    }
});

it('verifies views use singular kebab case naming', function () {
    $stubsDir = __DIR__.'/../../stubs';

    if (! is_dir($stubsDir)) {
        $this->markTestSkipped('Stubs directory not found');

        return;
    }

    $viewFiles = [
        'view.create.stub',
        'view.edit.stub',
        'view.index.stub',
        'view.show.stub',
        'controller.stub', // Controller has view() calls too
    ];

    $foundViewCalls = false;

    foreach ($viewFiles as $stubFile) {
        $stubPath = "{$stubsDir}/{$stubFile}";

        if (file_exists($stubPath)) {
            $content = file_get_contents($stubPath);

            // Check that views use kebab_name for Laravel view() helper calls
            if (preg_match('/return view\(/', $content)) {
                expect($content)->toContain('{{ kebab_name }}');
                $foundViewCalls = true;
            }
        }
    }

    // Ensure we actually tested something
    expect($foundViewCalls)->toBeTrue('No view() calls found in stub files');
});

it('tests route naming logic with Laravel helpers', function () {
    // Test the logic that generates plural kebab case route names
    $testCases = [
        'User' => 'users',
        'BlogPost' => 'blog-posts',
        'ProductCategory' => 'product-categories',
        'ApiToken' => 'api-tokens',
    ];

    foreach ($testCases as $input => $expected) {
        $result = Str::kebab(Str::plural($input));
        expect($result)->toBe($expected);
    }
});
