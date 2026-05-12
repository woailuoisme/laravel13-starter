<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/laravel.php';

set('repository', 'https://github.com/woailuoisme/laravel13-starter.git');
set('branch', 'main');
set('env', 'production');
set('app_dir', '/var/www/lunchbox');
set('rr_container', 'roadrunner');
set('shared_files', []);
set('shared_dirs', []);
set('writable_dirs', []);

host('production')
    ->setHostname('47.115.229.8')
    ->setRemoteUser('root')
    ->set('labels', ['stage' => 'production']);

function dockerCommand(string $command): string
{
    $container = escapeshellarg((string) get('rr_container'));
    $appDir = escapeshellarg((string) get('app_dir'));
    $script = sprintf('cd %s && %s', $appDir, $command);

    return sprintf(
        'docker exec %s bash -lc %s',
        $container,
        escapeshellarg($script),
    );
}

desc('Check that the remote RoadRunner container is available.');
task('pre-check', function (): void {
    writeln('开始环境检查...');

    run(sprintf('docker inspect %s > /dev/null 2>&1', escapeshellarg((string) get('rr_container'))));

    writeln('环境检查通过');
});

desc('Update application code from the configured branch.');
task('update-code', function (): void {
    $branch = (string) get('branch');

    writeln(sprintf('正在拉取代码 (分支: %s)...', $branch));
    run(dockerCommand(sprintf('git reset --hard && git pull origin %s', escapeshellarg($branch))));
});

desc('Install Composer dependencies without dev packages.');
task('composer-install', function (): void {
    writeln('正在安装 Composer 依赖...');

    run(dockerCommand('composer install --no-dev --optimize-autoloader'));
});

desc('Run database migrations.');
task('migrate', function (): void {
    writeln('正在执行数据库迁移...');

    run(dockerCommand('php artisan migrate --force'));
});

desc('Clear and rebuild framework and Filament caches.');
task('optimize', function (): void {
    writeln('正在构建缓存并优化...');

    run(dockerCommand('php artisan optimize:clear && php artisan optimize && php artisan filament:optimize && php artisan octane:reload'));
});

desc('Restart queue workers and Horizon.');
task('restart-queues', function (): void {
    writeln('正在重启队列与 Horizon...');

    run(dockerCommand('php artisan queue:restart && php artisan horizon:terminate'));
});

desc('Regenerate Scribe documentation.');
task('scribe-docs', function (): void {
    writeln('正在重新生成 Scribe 文档...');

    run(dockerCommand('php artisan scribe:generate --force'));
});

desc('Quick deployment: code update and optimization only.');
task('quick', function (): void {
    $branch = (string) get('branch');

    run(dockerCommand(sprintf(
        'git pull origin %s && php artisan optimize && php artisan octane:reload',
        escapeshellarg($branch),
    )));
});

desc('Clear response and framework caches.');
task('clear-all', function (): void {
    writeln('清理所有响应和系统缓存...');

    run(dockerCommand('php artisan optimize:clear && php artisan responsecache:clear'));
});

desc('Reload Octane on the remote server.');
task('reload', function (): void {
    run(dockerCommand('php artisan octane:reload'));
});

task('deploy', [
    'pre-check',
    'update-code',
    'composer-install',
    'migrate',
    'optimize',
    'restart-queues',
    'scribe-docs',
]);

task('quick-deploy', [
    'update-code',
    'optimize',
]);

after('deploy:failed', 'deploy:unlock');
