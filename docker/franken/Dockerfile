FROM jiaoio/php8.5-dev:cli-alpine AS builder

# 注入 bun alpine 版，与基础镜像 libc 兼容
COPY --from=oven/bun:1-alpine /usr/local/bin/bun /usr/local/bin/bun

WORKDIR /app

# 优先复制 lockfile，依赖未变时直接命中缓存
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --classmap-authoritative --no-dev --no-scripts

COPY package.json bun.lock ./
RUN bun install --frozen-lockfile

COPY . .
# dump-autoload 替代 post-autoload-dump，跳过 package:discover（需数据库）
RUN composer dump-autoload --optimize --no-scripts && bun run build

# ---------------------------------------------------------
FROM jiaoio/php8.5:franken-alpine

WORKDIR /app

# 仅复制运行所需文件，node_modules 不进入最终镜像
COPY . .
COPY --from=builder /app/vendor ./vendor
COPY --from=builder /app/public/build ./public/build

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

ENV APP_PATH=/app \
    APP_PORT=8001 \
    APP_ENV=production \
    APP_DEBUG=false \
    TZ=Asia/Shanghai \
    OCTANE_SERVER=frankenphp \
    OCTANE_HOST=0.0.0.0 \
    OCTANE_WORKERS=4 \
    OCTANE_MAX_REQUESTS=500 \
    OCTANE_ADMIN_PORT=2019 \
    WATCH=false \
    OCTANE_POLL=false \
    OCTANE_HTTPS=false \
    OCTANE_HTTP_REDIRECT=false \
    OCTANE_LOG_LEVEL=info \
    COMPOSER_ALLOW_SUPERUSER=1 \
    MAGICK_THREAD_LIMIT=2 \
    CADDY_GLOBAL_OPTIONS='{"log":{"format":"console"}}' \
    PYTHONWARNINGS="ignore:pkg_resources is deprecated"

# 设置系统时区
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 复制 PHP 配置
COPY docker/franken/php.ini /usr/local/etc/php/conf.d/zz-laravel.ini

# 复制启动脚本和健康检查脚本
COPY docker/franken/startup.sh /usr/local/bin/startup.sh
COPY docker/franken/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/startup.sh /usr/local/bin/healthcheck.sh

HEALTHCHECK --interval=60s --timeout=10s --start-period=5s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh

EXPOSE ${APP_PORT} ${OCTANE_ADMIN_PORT}
WORKDIR $APP_PATH
USER www-data
CMD ["/usr/local/bin/startup.sh"]
