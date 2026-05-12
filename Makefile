.PHONY: link-rr dev dev-rr k6-root-smoke k6-root-load k6-smoke k6-mini-load

# 在项目根目录创建 rr 软链接，方便直接使用 RoadRunner。
# 如果系统里找不到 rr，就直接报错。
link-rr:
	@command -v rr > /dev/null 2>&1 && ln -sf $$(which rr) rr || (echo "Error: 'rr' not found in PATH" && exit 1)

# 启动传统开发环境：Laravel + 队列监听 + 日志 + Vite。
# 适合和 dev-rr 同时开启；Vite 监听所有网卡，浏览器才能访问。
dev:
	@npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" "php artisan serve" "php artisan queue:listen --tries=1 --timeout=0" "php artisan pail --timeout=0" "npm run dev -- --host 0.0.0.0" --names=server,queue,logs,vite --kill-others

# 启动 RoadRunner 后端、队列监听和本地调度器。
# 适合和前端 Vite 单独配合使用；这里不再启动 Vite。
dev-rr:
	@npx concurrently -c "#93c5fd,#c4b5fd,#fdba74" "php artisan octane:start --server=roadrunner --host=0.0.0.0 --rpc-port=6001 --port=8001" "php artisan queue:listen --tries=1 --timeout=0" "php artisan schedule:work" --names=server,queue,schedule --kill-others

k6-root-smoke:
	@node tests/Performance/k6/run.mjs smoke

k6-root-load:
	@node tests/Performance/k6/run.mjs mini-load

k6-smoke: k6-root-smoke

k6-mini-load: k6-root-load

composer-update-ignore:
	composer update --ignore-platform-reqs
