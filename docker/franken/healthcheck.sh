#!/bin/sh

# Laravel Octane with FrankenPHP 健康检查脚本
set -e

RED='\033[0;31m' GREEN='\033[0;32m' NC='\033[0m'
log() { printf '%b[%s] [HEALTH]%b %s\n' "$1" "$(date '+%Y-%m-%d %H:%M:%S %z')" "${NC}" "$2" >&2; }
log_info() { log "${GREEN}" "$1"; }
log_err() { log "${RED}" "$1"; }

APP_PATH="${APP_PATH:-/app}"

[ -f "${APP_PATH}/artisan" ] || {
    log_err "应用目录或 artisan 不存在"
    exit 1
}

if curl -f -s -m 5 "http://localhost:${APP_PORT:-8001}/up" >/dev/null 2>&1; then
    log_info "FrankenPHP/HTTP 服务正常"
    exit 0
fi

log_err "HTTP 服务无响应"
exit 1
