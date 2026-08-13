# 显示任务列表。
default:
    @just --list


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

# 导出 Postman 接口集合。
postman:
    php artisan export:postman --bearer="1|XXNKXXqJjfzG8XXSvXX1Q4pxxnkXmp8tT8TXXKXX"

# 格式化 PHP 代码。
fmt:
    mago format

# 运行代码静态检查（Mago Lint 与 Markdown 规范检查）。
lint:
    mago lint

# 自动修复代码格式与 Lint 问题。
fix:
    mago lint --fix --potentially-unsafe --format-after-fix --fail-on-remaining

# 运行代码全面检查（格式、Lint、静态分析）。
check:
    mago format --check
    mago lint
    mago analyze

# 运行 Markdown 规范检查。
markdownlint:
    rumdl check

# `markdownlint` 的别名。
rumdl: markdownlint


# 生成 IDE 辅助文件（Facade, Meta, Models 写回）。
ide-helper:
    @php artisan ide-helper:generate
    @php artisan ide-helper:meta
    @php artisan ide-helper:models --write --no-interaction

test:
     php artisan test

# 使用 Laravel 内建加密工具加密 .env.production 文件（生成 .env.production.encrypted）。
env-encrypt:
    php artisan env:encrypt --env=production

# 使用 Laravel 内建加密工具解密 .env.production.encrypted 文件。
env-decrypt:
    php artisan env:decrypt --env=production
