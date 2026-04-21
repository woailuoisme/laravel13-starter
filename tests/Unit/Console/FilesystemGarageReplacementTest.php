<?php

declare(strict_types=1);

use App\Console\Commands\ClearBucketCommand;
use Illuminate\Console\Attributes\Signature;

it('defaults the clear bucket command to garage', function (): void {
    $signature = (new ReflectionClass(ClearBucketCommand::class))
        ->getAttributes(Signature::class)[0]
        ->getArguments()[0];

    expect($signature)->toContain('{--disk=garage');
});

it('uses garage when init db clears the bucket', function (): void {
    $source = file_get_contents(app_path('Console/Commands/InitDb.php'));

    expect($source)->toContain("'--disk' => 'garage'");
    expect($source)->not->toContain('minio');
});
