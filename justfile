# 显示任务列表。
default:
    @just --list

docker_compose := "docker compose -f docker/roadrunner/docker-compose.yml"
docker_compose_fpm := "docker compose -f docker/fpm/docker-compose.yml"
docker_compose_franken := "docker compose -f docker/franken/docker-compose.yml"

# 创建项目根目录的 rr 软链接。
link-rr:
    @command -v rr > /dev/null 2>&1 && ln -sf $(which rr) rr || (echo "Error: 'rr' not found in PATH" && exit 1)

# 启动本地开发环境：Laravel、Horizon、日志和 Vite。
dev:
    @npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" "php artisan serve" "php artisan horizon" "php artisan pail --timeout=0" "npm run dev -- --host 0.0.0.0" --names=server,horizon,logs,vite --kill-others

# 启动 RoadRunner、Horizon 和调度器。
dev-rr:
    @npx concurrently -c "#93c5fd,#c4b5fd,#fdba74" "php artisan octane:start --server=roadrunner --host=0.0.0.0 --rpc-port=6001 --port=8001" "php artisan horizon" "php artisan schedule:work" --names=server,horizon,schedule --kill-others

# 启动带文件监听的 RoadRunner 开发环境。
dev-rr-watch:
    @npx concurrently -c "#93c5fd,#c4b5fd,#fdba74" "php artisan octane:start --server=roadrunner --host=0.0.0.0 --rpc-port=6001 --port=8001 --watch" "php artisan horizon:watch --without-tty" "php artisan schedule:work" --names=server,horizon,schedule --kill-others

# 创建 Docker 外部网络。
docker-net:
    @docker network inspect "$${DOCKER_NETWORK:-backend}" >/dev/null 2>&1 || docker network create "$${DOCKER_NETWORK:-backend}"

# 构建 Docker 应用镜像。
docker-build:
    @{{docker_compose}} build app

# 启动 Docker 应用、Horizon 和调度器。
docker-up: docker-net
    @{{docker_compose}} up -d app horizon schedule --force-recreate --no-deps --remove-orphans

# 停止 Docker 服务。
docker-down:
    @{{docker_compose}} down

# 查看 Docker 服务状态。
docker-ps:
    @{{docker_compose}} ps

# 跟踪 Docker 服务日志。
docker-logs service="app":
    @{{docker_compose}} logs -f {{service}}

# 进入 Docker 应用容器。
docker-shell:
    @{{docker_compose}} exec app sh

# 在 Docker 应用容器中执行 Artisan。
docker-artisan *args:
    @{{docker_compose}} exec app php artisan {{args}}

# 构建 FPM 应用镜像。
docker-fpm-build:
    @{{docker_compose_fpm}} build app

# 启动 FPM 应用容器。
docker-fpm-up: docker-net
    @{{docker_compose_fpm}} up -d app

# 停止 FPM 应用容器。
docker-fpm-down:
    @{{docker_compose_fpm}} down

# 跟踪 FPM 应用日志。
docker-fpm-logs:
    @{{docker_compose_fpm}} logs -f app

# 进入 FPM 应用容器。
docker-fpm-shell:
    @{{docker_compose_fpm}} exec app sh

# 构建 FrankenPHP 应用镜像。
docker-franken-build:
    @{{docker_compose_franken}} build app

# 启动 FrankenPHP 应用容器。
docker-franken-up: docker-net
    @{{docker_compose_franken}} up -d app

# 停止 FrankenPHP 应用容器。
docker-franken-down:
    @{{docker_compose_franken}} down

# 跟踪 FrankenPHP 应用日志。
docker-franken-logs:
    @{{docker_compose_franken}} logs -f app

# 进入 FrankenPHP 应用容器。
docker-franken-shell:
    @{{docker_compose_franken}} exec app sh

# 执行 k6 smoke 测试。
k6-root-smoke:
    @node tests/Performance/k6/run.mjs smoke

# 执行 k6 mini-load 测试。
k6-root-load:
    @node tests/Performance/k6/run.mjs mini-load

# `k6-smoke` 的别名。
k6-smoke: k6-root-smoke

# `k6-mini-load` 的别名。
k6-mini-load: k6-root-load

# 忽略平台要求更新依赖。
composer-update-ignore:
    composer update --ignore-platform-reqs

# 运行 Pint 格式化。
pint:
    ./vendor/bin/pint -p

# 运行 Pint 并输出并行结果。
pint-dirty:
    ./vendor/bin/pint --parallel --dirty --test

# 运行 Pint 的脏检查模式。
pint-dirty-check:
    ./vendor/bin/pint --parallel --dirty --test

# 导出 Postman 接口集合。
postman:
    php artisan export:postman --bearer="1|XXNKXXqJjfzG8XXSvXX1Q4pxxnkXmp8tT8TXXKXX"
