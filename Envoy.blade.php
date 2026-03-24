@servers([
    'web' => ['root@47.115.229.8'],
    'local' => 'localhost',
])

@setup
    $appDir = '/var/www/lunchbox';
    $dockerDir = '/var/docker/lunchbox';
    $branch = $branch ?? 'main';
    $env = $env ?? 'production';
    $rrContainer = 'roadrunner';
    
    // 助手指令：进入应用目录执行 Docker 指令
    $run = "docker exec $rrContainer bash -c \"cd $appDir && ";
@endsetup

{{-- 完整部署流程 --}}
@story('deploy')
    pre-check
    update-code
    composer-install
    migrate
    optimize
    restart-queues
    scribe-docs
@endstory

{{-- 快速部署（仅更新代码和缓存） --}}
@story('quick-deploy')
    update-code
    optimize
@endstory

{{-- 极简部署 --}}
@task('quick', ['on' => 'web'])
    {{ $run }} git pull origin {{ $branch }} && php artisan optimize && php artisan octane:reload\"
@endtask

{{-- 1. 环境前置检查 --}}
@task('pre-check', ['on' => 'web', 'confirm' => true])
    echo "开始环境检查..."
    if ! docker inspect {{ $rrContainer }} &> /dev/null; then
        echo "错误：容器 {{ $rrContainer }} 未运行"
        exit 1
    fi
    echo "环境检查通过"
@endtask

{{-- 2. 更新代码 --}}
@task('update-code', ['on' => 'web'])
    echo "正在拉取代码 (分支: {{ $branch }})..."
    {{ $run }} git reset --hard && git pull origin {{ $branch }}\"
@endtask

{{-- 3. 安装依赖 --}}
@task('composer-install', ['on' => 'web'])
    echo "正在安装 Composer 依赖..."
    {{ $run }} composer install --no-dev --optimize-autoloader\"
@endtask

{{-- 4. 数据库迁移 --}}
@task('migrate', ['on' => 'web'])
    echo "正在执行数据库迁移..."
    {{ $run }} php artisan migrate --force\"
@endtask

{{-- 5. 缓存与性能优化 --}}
@task('optimize', ['on' => 'web'])
    echo "正在构建缓存并优化..."
    {{ $run }} php artisan optimize:clear && php artisan optimize && php artisan filament:optimize && php artisan octane:reload\"
@endtask

{{-- 6. 重启异步服务 --}}
@task('restart-queues', ['on' => 'web'])
    echo "正在重启队列与 Horizon..."
    {{ $run }} php artisan queue:restart && php artisan horizon:terminate\"
@endtask

{{-- 7. 重新生成文档 --}}
@task('scribe-docs', ['on' => 'web'])
    echo "正在重新生成 Scribe 文档..."
    {{ $run }} php artisan scribe:generate --force\"
@endtask

{{-- 工具：清理所有缓存 --}}
@task('clear-all', ['on' => 'web'])
    echo "清理所有响应和系统缓存..."
    {{ $run }} php artisan optimize:clear && php artisan responsecache:clear\"
@endtask

{{-- 工具：手动重启 Octane --}}
@task('reload', ['on' => 'web'])
    {{ $run }} php artisan octane:reload\"
@endtask

{{-- 错误处理 --}}
@error
    echo "部署失败！任务：$task";
@enderror

{{-- 成功处理 --}}
@success
    date_default_timezone_set('Asia/Shanghai');
    echo "部署成功！\n环境：$env\n分支：$branch\n完成时间：" . date('Y-m-d H:i:s');
@endsuccess
