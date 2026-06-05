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
FROM jiaoio/php8.5:fpm-alpine

WORKDIR /app

# 仅复制运行所需文件，node_modules 不进入最终镜像
COPY . .
COPY --from=builder /app/vendor ./vendor
COPY --from=builder /app/public/build ./public/build

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

ENV APP_PATH=/app \
    APP_ENV=production \
    APP_DEBUG=false \
    TZ=Asia/Shanghai \
    COMPOSER_ALLOW_SUPERUSER=1 \
    MAGICK_THREAD_LIMIT=2 \
    PYTHONWARNINGS="ignore:pkg_resources is deprecated"

# 设置系统时区
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# FPM 健康检查需要 cgi-fcgi 请求 /ping。
RUN apk add --no-cache fcgi

# 复制 PHP-FPM 配置
COPY docker/fpm/php.ini /usr/local/etc/php/conf.d/zz-laravel.ini
COPY docker/fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

# 复制启动脚本和健康检查脚本
COPY docker/fpm/startup.sh /usr/local/bin/startup.sh
COPY docker/fpm/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/startup.sh /usr/local/bin/healthcheck.sh

HEALTHCHECK --interval=60s --timeout=5s --start-period=10s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh

EXPOSE 9000
WORKDIR $APP_PATH
CMD ["/usr/local/bin/startup.sh"]
