#!/bin/sh

# PHP-FPM 启动脚本
set -e

# 日志工具
RED='\033[0;31m' GREEN='\033[0;32m' BLUE='\033[0;34m' NC='\033[0m'
log() { printf '%b[%s] [%s]%b %s\n' "$1" "$(date '+%Y-%m-%d %H:%M:%S %z')" "$2" "${NC}" "$3"; }
log_info()    { log "${BLUE}" "INFO" "$1"; }
log_success() { log "${GREEN}" "SUCCESS" "$1"; }
log_error()   { log "${RED}" "ERROR" "$1"; }

APP_PATH="${APP_PATH:-/app}"

if [ ! -f "${APP_PATH}/artisan" ]; then
    log_error "artisan 文件不存在: ${APP_PATH}/artisan"
    exit 1
fi

cd "${APP_PATH}"
log_success "应用目录检查通过"
log_info "启动 PHP-FPM..."

exec php-fpm --nodaemonize
