<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/laravel.php';

/**
 * 部署执行序列说明：
 * - deploy：完整发布流程，依次执行 pre-check、update-code、composer-install、
 *   migrate、optimize、restart-queues、scribe-docs。
 * - quick-deploy：轻量发布流程，只执行 update-code、optimize。
 * - quick：极简单任务流程，在容器内拉取代码后执行 optimize 与 octane:reload。
 * - clear-all / reload：独立维护任务，不属于完整发布流程。
 *
 * 所有远端应用命令都通过 dockerCommand() 进入 RoadRunner 容器，并在 app_dir
 * 指向的应用目录中执行。
 */
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
    $container = escapeshellarg((string) get('rr_container'));

    writeln('开始环境检查...');

    run(sprintf(
        'docker inspect %1$s > /dev/null 2>&1 || (echo "错误：容器 %1$s 未运行或不可访问" && exit 1)',
        $container,
    ));

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

desc('Quick deploy story: update code and rebuild caches.');
task('quick-deploy', [
    'update-code',
    'optimize',
]);

after('deploy:failed', 'deploy:unlock');
