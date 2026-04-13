.PHONY: link-rr dev dev-rr stress-local stress-8000 stress-8001

# 在项目根目录创建 rr 软链接，方便直接使用 RoadRunner。
# 如果系统里找不到 rr，就直接报错。
link-rr:
	@command -v rr > /dev/null 2>&1 && ln -sf $$(which rr) rr || (echo "Error: 'rr' not found in PATH" && exit 1)

# 启动传统开发环境：Laravel + 队列监听 + 日志 + Vite。
# 适合和 dev-rr 同时开启；Vite 监听所有网卡，浏览器才能访问。
dev:
	@npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" "php artisan serve" "php artisan queue:listen --tries=1 --timeout=0" "php artisan pail --timeout=0" "npm run dev -- --host 0.0.0.0" --names=server,queue,logs,vite --kill-others

# 启动 RoadRunner 后端。
# 适合和 dev 同时开启；这里只跑 Octane，不再启动 Vite、队列和日志。
dev-rr:
	@npx concurrently -c "#93c5fd" "php artisan octane:start --server=roadrunner --host=0.0.0.0 --rpc-port=6001 --port=8001" --names=server --kill-others

# 运行本地压力测试，检查 8000 和 8001 两个地址是否都能稳定响应。
stress-local:
	@php artisan test --compact --filter=LocalServerStressTest

# 压测 8000，使用接近生产的并发和持续时间。
stress-8000:
	@./vendor/bin/pest stress http://127.0.0.1:8000 --concurrency=10 --duration=60

# 压测 8001，使用接近生产的并发和持续时间。
stress-8001:
	@./vendor/bin/pest stress http://127.0.0.1:8001 --concurrency=10 --duration=60
