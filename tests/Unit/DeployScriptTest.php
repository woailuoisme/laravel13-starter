<?php

function runDepCommand(string $arguments): string
{
    $dep = mb_trim((string) shell_exec('command -v dep'));

    expect($dep)->not->toBe('');

    $command = sprintf(
        '%s --file %s %s 2>&1',
        escapeshellarg($dep),
        escapeshellarg(base_path('deploy.php')),
        $arguments,
    );

    return (string) shell_exec($command);
}

it('registers the envoy-equivalent tasks', function () {
    $output = runDepCommand('list');

    expect($output)
        ->toContain('pre-check')
        ->toContain('update-code')
        ->toContain('composer-install')
        ->toContain('migrate')
        ->toContain('optimize')
        ->toContain('restart-queues')
        ->toContain('scribe-docs')
        ->toContain('quick')
        ->toContain('clear-all')
        ->toContain('reload');
});

it('keeps the deploy story order aligned with Envoy', function () {
    $output = runDepCommand('tree deploy');

    expect($output)
        ->toContain('pre-check')
        ->toContain('update-code')
        ->toContain('composer-install')
        ->toContain('migrate')
        ->toContain('optimize')
        ->toContain('restart-queues')
        ->toContain('scribe-docs');

    expect($output)->toContain("├── pre-check");
    expect($output)->toContain("└── scribe-docs");
});

it('keeps the quick deploy story minimal', function () {
    $output = runDepCommand('tree quick-deploy');

    expect($output)
        ->toContain('update-code')
        ->toContain('optimize')
        ->not->toContain('composer-install')
        ->not->toContain('migrate');
});
