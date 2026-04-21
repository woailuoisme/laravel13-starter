<?php

declare(strict_types=1);

it('includes a garage filesystem disk with s3-compatible defaults', function (): void {
    expect(config('filesystems.disks.garage'))->toMatchArray([
        'driver' => 's3',
        'region' => 'garage',
        'endpoint' => 'http://127.0.0.1:3900',
        'use_path_style_endpoint' => true,
        'throw' => false,
        'report' => false,
    ]);

    expect(config('filesystems.disks.minio'))->toBeNull();
});
