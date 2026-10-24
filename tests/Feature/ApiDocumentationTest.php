<?php

use Illuminate\Support\Facades\Route;

it('documents every registered API operation', function () {
    $specification = json_decode(file_get_contents(public_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);

    $documented = collect($specification['paths'])
        ->flatMap(fn (array $path, string $uri) => collect($path)
            ->keys()
            ->filter(fn (string $method) => in_array($method, ['get', 'post', 'put', 'patch', 'delete']))
            ->map(fn (string $method) => strtoupper($method).' '.ltrim($uri, '/')))
        ->sort()
        ->values()
        ->all();

    $registered = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        ->flatMap(fn ($route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => $method.' '.$route->uri()))
        ->sort()
        ->values()
        ->all();

    expect($specification['openapi'])->toBe('3.0.3')
        ->and($documented)->toBe($registered);
});

it('resolves every OpenAPI component reference', function () {
    $specification = json_decode(file_get_contents(public_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);
    $values = new RecursiveIteratorIterator(new RecursiveArrayIterator($specification));

    foreach ($values as $key => $value) {
        if ($key === '$ref') {
            expect(data_get($specification, str_replace('/', '.', substr($value, 2))))->not->toBeNull();
        }
    }
});

it('marks authenticated routes as secured in OpenAPI', function () {
    $specification = json_decode(file_get_contents(public_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/')) {
            continue;
        }

        $requiresToken = in_array('auth:sanctum', $route->gatherMiddleware());

        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $security = $specification['paths']['/'.$route->uri()][strtolower($method)]['security'] ?? [];
            expect($security !== [])->toBe($requiresToken);
        }
    }
});

it('renders the interactive API reference', function () {
    $this->get('/docs')
        ->assertOk()
        ->assertSee('swagger-ui-bundle.js')
        ->assertSee('openapi.json');
});
