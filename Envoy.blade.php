@servers([
    'web' => ['root@47.115.229.8'],
    'workers' => ['root@8.155.171.54'],
    'local' => 'localhost',
])

@options(['ssh' => '-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null'])

{{-- 部署前初始化配置 --}}
@setup
$appDir = '/var/www/lunchbox';
$dockerDir = '/var/docker/lunchbox';
$scriptDir = '/var/script/lunchbox';
$branch = isset($branch) ? $branch : 'main';
$env = isset($env) ? $env : 'production';
$container = isset($container) ? $container : 'fpm';
$rrContainer = 'roadrunner';
$logDir = '/var/log/envoy';
$logFile = "{$logDir}/deploy-{$env}-{$branch}-" . date('YmdHis') . ".log";
@endsetup

{{-- 完整部署流程（含备份和检查） --}}
{{-- @story('deploy') --}}
{{--    pre-check --}}
{{--    backup-data --}}
{{--    update-code --}}
{{--    install-dependencies --}}
{{--    run-migrations --}}
{{--    restart-services --}}
{{--    cleanup --}}
{{--    health-check --}}
{{-- @endstory --}}

{{-- 快速部署（跳过备份和部分检查） --}}
@story('quick-deploy')
pre-check
app:git:pull
composer-install
migrate
scribe
{{--    restart-services --}}
@endstory

{{-- 本地语法测试任务 --}}
@task('test-local', ['on' => 'local'])
echo "Envoy 脚本语法检查通过"
echo "应用目录: {{ $appDir }}"
echo "部署分支: {{ $branch }}"
echo "环境: {{ $env }}"
echo "容器: {{ $container }}"
echo "日志文件: {{ $logFile }}"
@endtask

@task('test', ['on' => 'web'])
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan --version"
@endtask

@task('permission', ['on' => 'web'])
cd {{ $appDir }} && mkdir -p bootstrap/cache && php artisan storage:link && chmod 0766 bootstrap/cache storage
cd /var/www && chown -R www-data:www-data {{ $appDir }}
@endtask

@task('app:git:pull', ['on' => 'web'])
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && git reset --hard && git pull origin main"
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan filament:optimize-clear && php artisan filament:optimize"
{{--docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan octane:reload"--}}
@endtask


@task('filament', ['on' => 'web'])
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan filament:optimize-clear && php artisan filament:optimize"
@endtask

@task('app:deploy', ['on' => 'web'])
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && git reset --hard && git pull origin main"
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && rm -rf bootstrap/cache/*.php && COMPOSER_CACHE_DIR=/dev/null composer install --no-dev --optimize-autoloader"
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan filament:optimize-clear && php artisan filament:optimize"
{{--docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan octane:reload"--}}
{{-- docker exec {{ $container }} bash -c "cd {{ $appDir }} && cp .env.production .env" --}}
{{-- # Change APP_ENV and APP_DEBUG to be production ready --}}
@endtask

@task('migrate', ['on' => 'web'])
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan migrate"
@endtask

@task('scribe', ['on' => 'web'])
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan scribe:generate"
@endtask

@task('reload-rr', ['on' => 'web'])
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan octane:reload"
@endtask

@task('restart-rr', ['on' => 'web'])
cd /var/docker/lunchbox && docker compose restart roadrunner --no-deps
@endtask

@task('clear:file', ['on' => 'web'])
docker exec {{ $container }} bash -c "cd {{ $appDir }} && rm -rf bootstrap/cache/*.php"
@endtask

@task('std-deploy', ['on' => 'web'])
{{-- . 推荐的“生产环境标准指令” --}}
{{-- # 1.  为了保证 100% 的准确性，建议在你的部署流水线（Deploy Script）中使用以下组合： --}}
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && git reset --hard && git pull origin main"
{{-- # 2. 彻底清除所有旧缓存（包括配置、路由、视图、Filament、事件等） php artisan optimize:clear --}}
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan optimize:clear"
{{-- # 3. 重新构建全新的缓存 php artisan optimize && php artisan filament:optimize --}}
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan optimize && php artisan filament:optimize"
{{-- # 4. 让 Octane 读取这些新缓存 php artisan octane:reload --}}
docker exec {{ $rrContainer }} bash -c "cd {{ $appDir }} && php artisan octane:reload"
@endtask

@task('filament-product', ['on' => 'web'])
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan filament:optimize-clear"
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan filament:cache-components"
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan icons:cache"
@endtask

@task('docker:git:pull', ['on' => 'web'])
cd {{ $dockerDir }} && git reset --hard && git pull origin main
@endtask

@task('docker:re:up', ['on' => 'web'])
cd {{ $dockerDir }} && docker compose down && docker compose up -d
@endtask

{{-- 前置环境检查 --}}
@task('pre-check', ['on' => 'web', 'confirm' => true])
echo "开始环境前置检查（$(date '+%Y-%m-%d %H:%M:%S')）"

# 检查应用目录是否存在
if [ ! -d "{{ $appDir }}" ]; then
echo "错误：应用目录 {{ $appDir }} 不存在"
exit 1
fi

# 检查容器是否运行
if ! docker inspect {{ $container }} &> /dev/null; then
echo "错误：容器 {{ $container }} 未运行"
exit 1
fi

# 检查Git是否可用
if ! command -v git &> /dev/null; then
echo "错误：未安装git工具"
exit 1
fi

echo "环境检查通过"
@endtask

{{-- 数据备份 --}}
@task('backup', ['on' => 'web'])
echo "开始数据备份（$(date '+%Y-%m-%d %H:%M:%S')）"
# 数据库备份
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan backup:run --only-db"
if [ $? -eq 0 ]; then
echo "数据库备份成功"
else
echo "数据库备份失败，继续部署（非致命错误）"
fi
@endtask

{{-- 更新代码 --}}
@task('update-code', ['on' => 'web'])
echo "开始拉取代码（分支：{{ $branch }}）（$(date '+%Y-%m-%d %H:%M:%S')）"
cd {{ $appDir }}

# 拉取前先fetch避免冲突
git fetch origin {{ $branch }}
git pull origin {{ $branch }}

if [ $? -ne 0 ]; then
echo "代码拉取失败，请手动解决冲突"
exit 1
fi
echo "代码拉取完成"
@endtask

{{-- 安装依赖 --}}
@task('composer-install', ['on' => 'web'])
echo "开始安装依赖（$(date '+%Y-%m-%d %H:%M:%S')）"
# Composer依赖（超时10分钟）
timeout 600 docker exec {{ $container }} bash -c "cd {{ $appDir }} && rm -rf bootstrap/cache/*.php &&
COMPOSER_CACHE_DIR=/dev/null composer install --no-dev "
if [ $? -ne 0 ]; then
echo "Composer依赖安装失败"
exit 1
fi
@endtask


{{-- 重启服务 --}}
@task('restart-services', ['on' => 'web'])
echo "重启服务（$(date '+%Y-%m-%d %H:%M:%S')）"

# 重启队列
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan queue:restart"

# 清理和缓存优化
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan optimize:clear"
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan config:cache"
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan route:cache"

@if ($env === 'production')
    docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan horizon:terminate"
    echo "生产环境Horizon已重启"
@endif

echo "所有服务重启完成"
@endtask

{{-- 清理冗余文件 --}}
@task('cleanup', ['on' => 'web'])
echo "开始清理冗余文件（$(date '+%Y-%m-%d %H:%M:%S')）"

# 清理Composer缓存
docker exec {{ $container }} bash -c "cd {{ $appDir }} && composer clear-cache"

# 清理旧的备份文件（保留最近7天）
find /tmp -name "db-backup-*.sql" -mtime +7 -delete 2>/dev/null || true
find {{ $appDir }} -name ".env.backup-*" -mtime +7 -delete 2>/dev/null || true

echo "清理完成"
@endtask

@task('clear-response-cache', ['on' => 'web'])
# 清理response cache
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan responsecache:clear"
@endtask

{{-- 健康检查 --}}
@task('health-check', ['on' => 'web'])
echo "开始健康检查（$(date '+%Y-%m-%d %H:%M:%S')）"

# 应用状态检查
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan about"

# 队列状态检查
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan queue:monitor --duration=5
--interval=1"

echo "健康检查完成"
@endtask

{{-- 仅重启队列 --}}
@task('restart-queues', ['on' => 'web'])
echo "单独重启队列服务"
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan queue:restart"
echo "队列重启完成"
@endtask

{{-- 仅运行迁移 --}}
@task('migrate', ['on' => 'web'])
echo "单独执行数据库迁移"
docker exec {{ $container }} bash -c "cd {{ $appDir }} && php artisan migrate --force"
echo "迁移完成"
@endtask


{{-- 错误处理 --}}
@error
echo "部署失败（任务：$task），详情见日志：$logFile";
@enderror

{{-- 成功处理 --}}
@success
date_default_timezone_set('Asia/Shanghai');
echo "部署成功！\n环境：$env\n分支：$branch\n完成时间：" . date('Y-m-d H:i:s');
@endsuccess
