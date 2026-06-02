FROM jiaoio/php8.5-dev:cli-alpine AS builder

# 注入 bun alpine 版，与基础镜像 libc 兼容
COPY --from=oven/bun:1-alpine /usr/local/bin/bun /usr/local/bin/bun

WORKDIR /app

# 优先复制 lockfile，依赖未变时直接命中缓存
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts

COPY package.json bun.lock ./
RUN bun install --frozen-lockfile

COPY . .
# dump-autoload 替代 post-autoload-dump，跳过 package:discover（需数据库）
# filament:upgrade 发布 assets 并清缓存，应在容器启动后通过 entrypoint 执行
RUN composer dump-autoload --optimize && bun run build

# ---------------------------------------------------------
FROM jiaoio/php8.5:roadrunner-alpine

WORKDIR /app

# 仅复制运行所需文件，node_modules 不进入最终镜像
COPY . .
COPY --from=builder /app/vendor ./vendor
COPY --from=builder /app/public/build ./public/build

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE 8000
USER www-data

CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000"]
