.PHONY: link-rr dev dev-rr dev-rr-watch k6-root-smoke k6-root-load k6-smoke k6-mini-load

# 在项目根目录创建 rr 软链接，方便直接使用 RoadRunner。
# 如果系统里找不到 rr，就直接报错。
link-rr:
	@command -v rr > /dev/null 2>&1 && ln -sf $$(which rr) rr || (echo "Error: 'rr' not found in PATH" && exit 1)

# 启动传统开发环境：Laravel + Horizon + 日志 + Vite。
# 适合和 dev-rr 同时开启；Vite 监听所有网卡，浏览器才能访问。
dev:
	@npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" "php artisan serve" "php artisan horizon" "php artisan pail --timeout=0" "npm run dev -- --host 0.0.0.0" --names=server,horizon,logs,vite --kill-others

# 启动 RoadRunner 后端、Horizon 和本地调度器。
# 适合和前端 Vite 单独配合使用；这里不再启动 Vite。
dev-rr:
	@npx concurrently -c "#93c5fd,#c4b5fd,#fdba74" "php artisan octane:start --server=roadrunner --host=0.0.0.0 --rpc-port=6001 --port=8001" "php artisan horizon" "php artisan schedule:work" --names=server,horizon,schedule --kill-others

dev-rr-watch:
	@npx concurrently -c "#93c5fd,#c4b5fd,#fdba74" "php artisan octane:start --server=roadrunner --host=0.0.0.0 --rpc-port=6001 --port=8001 --watch" "php artisan horizon:watch --without-tty" "php artisan schedule:work" --names=server,horizon,schedule --kill-others

k6-root-smoke:
	@node tests/Performance/k6/run.mjs smoke

k6-root-load:
	@node tests/Performance/k6/run.mjs mini-load

k6-smoke: k6-root-smoke

k6-mini-load: k6-root-load

composer-update-ignore:
	composer update --ignore-platform-reqs
pint:
	./vendor/bin/pint -p

pint-dirty:
	./vendor/bin/pint --parallel --dirty --test

pint-dirty-check:
	./vendor/bin/pint --parallel --dirty --test

postman:
	php artisan export:postman --bearer="1|XXNKXXqJjfzG8XXSvXX1Q4pxxnkXmp8tT8TXXKXX"
