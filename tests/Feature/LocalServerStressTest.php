<?php

declare(strict_types=1);

use function Pest\Stressless\stress;

it('handles the traditional local server', function (): void {
    $result = stress('http://127.0.0.1:8000')
        ->concurrently(requests: 2)
        ->for(3)
        ->seconds();

    expect($result->requests()->failed()->count())->toBe(0);
});

it('handles the roadrunner local server', function (): void {
    $result = stress('http://127.0.0.1:8001')
        ->concurrently(requests: 2)
        ->for(3)
        ->seconds();

    expect($result->requests()->failed()->count())->toBe(0);
});
