<?php

declare(strict_types=1);

test('the root endpoint returns a successful json response', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'name',
            'env',
            'version',
            'status',
            'timestamp',
        ])
        ->assertJsonPath('status', 'healthy');
});

test('the healthcheck endpoint returns a successful json response', function () {
    $response = $this->get('/up');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'timestamp',
            'services' => [
                'database',
            ],
        ])
        ->assertJsonPath('status', 'up')
        ->assertJsonPath('services.database', 'ok');
});

test('the readiness endpoint returns a successful json response', function () {
    $response = $this->get('/ready');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'timestamp',
        ])
        ->assertJsonPath('status', 'ok');
});

test('non-existent web routes return a standardized json 404 response', function () {
    $response = $this->get('/non-existent-url-random-route');

    $response
        ->assertNotFound()
        ->assertJsonStructure([
            'success',
            'code',
            'message',
            'exception',
            'timestamp',
        ])
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 404)
        ->assertJsonPath('exception', 'NotFoundHttpException');
});

test('non-existent api routes return a standardized json 404 response', function () {
    $response = $this->get('/api/v1/non-existent-api-endpoint');

    $response
        ->assertNotFound()
        ->assertJsonStructure([
            'success',
            'code',
            'message',
            'exception',
            'timestamp',
        ])
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 404)
        ->assertJsonPath('exception', 'NotFoundHttpException');
});
