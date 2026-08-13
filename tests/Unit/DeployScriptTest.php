<?php

function runDepCommand(string $arguments): string
{
    $dep = mb_trim((string) shell_exec('command -v dep'));

    expect($dep)->not->toBeEmpty();

    $command = sprintf(
        '%s --file %s %s 2>&1',
        escapeshellarg($dep),
        escapeshellarg(base_path('deploy.php')),
        $arguments,
    );

    return (string) shell_exec($command);
}

it('registers the deployer tasks', function () {
    $output = runDepCommand('list');

    expect($output)->toContain('pre-check')
        ->toContain('update-code')
        ->toContain('composer-install')
        ->toContain('migrate')
        ->toContain('optimize')
        ->toContain('restart-queues')
        ->toContain('scribe-docs')
        ->toContain('quick')
        ->toContain('quick-deploy')
        ->toContain('Quick deploy story: update code and rebuild caches.')
        ->toContain('clear-all')
        ->toContain('reload');
});

it('reports a clear error when the RoadRunner container is unavailable', function () {
    $script = file_get_contents(base_path('deploy.php'));

    expect($script)->toContain('docker inspect')
        ->toContain('错误：容器 %1$s 未运行或不可访问');
});

it('keeps the full deploy story order stable', function () {
    $output = runDepCommand('tree deploy');

    expect($output)->toContain('pre-check')
        ->toContain('update-code')
        ->toContain('composer-install')
        ->toContain('migrate')
        ->toContain('optimize')
        ->toContain('restart-queues')
        ->toContain('scribe-docs');

    expect($output)->toContain('├── pre-check');
    expect($output)->toContain('└── scribe-docs');
});

it('keeps the quick deploy story minimal', function () {
    $output = runDepCommand('tree quick-deploy');

    expect($output)->toContain('update-code')
        ->toContain('optimize')
        ->not->toContain('composer-install')
        ->not->toContain('migrate');
});
