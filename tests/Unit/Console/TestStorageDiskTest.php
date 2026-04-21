<?php

declare(strict_types=1);

use App\Console\Commands\TestStorageDisk;

it('includes garage in the available storage disks', function (): void {
    $command = app(TestStorageDisk::class);
    $method = new ReflectionMethod($command, 'getAvailableDisks');
    $method->setAccessible(true);

    $availableDisks = $method->invoke($command);

    expect($availableDisks)->toContain('garage');
});
