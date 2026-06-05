#!/bin/sh

# Laravel Octane with FrankenPHP 启动脚本
set -e

# 日志工具
RED='\033[0;31m' GREEN='\033[0;32m' BLUE='\033[0;34m' NC='\033[0m'
log() { printf '%b[%s] [%s]%b %s\n' "$1" "$(date '+%Y-%m-%d %H:%M:%S %z')" "$2" "${NC}" "$3"; }
log_info()    { log "${BLUE}" "INFO" "$1"; }
log_success() { log "${GREEN}" "SUCCESS" "$1"; }
log_error()   { log "${RED}" "ERROR" "$1"; }

APP_PATH="${APP_PATH:-/app}"
APP_ENV="${APP_ENV:-docker}"

if [ ! -f "${APP_PATH}/artisan" ]; then
    log_error "artisan 文件不存在: ${APP_PATH}/artisan"
    exit 1
fi

cd "${APP_PATH}"
log_success "应用目录检查通过"
log_info "启动 Laravel Octane with FrankenPHP..."

set -- php artisan octane:frankenphp \
    "--port=${APP_PORT}" \
    "--host=${OCTANE_HOST}" \
    "--workers=${OCTANE_WORKERS}" \
    "--admin-port=${OCTANE_ADMIN_PORT}" \
    "--max-requests=${OCTANE_MAX_REQUESTS}" \
    "--env=${APP_ENV}" \
    "--log-level=${OCTANE_LOG_LEVEL}"

if [ "${WATCH}" = "true" ]; then
    set -- "$@" --watch
fi

if [ "${OCTANE_POLL}" = "true" ]; then
    set -- "$@" --poll
fi

if [ "${OCTANE_HTTPS}" = "true" ]; then
    set -- "$@" --https
fi

if [ "${OCTANE_HTTP_REDIRECT}" = "true" ]; then
    set -- "$@" --http-redirect
fi

log_info "完整命令: $*"
exec "$@"
