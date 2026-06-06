#!/bin/sh

# PHP-FPM 健康检查脚本
set -e

RED='\033[0;31m' GREEN='\033[0;32m' NC='\033[0m'
log() { printf '%b[%s] [HEALTH]%b %s\n' "$1" "$(date '+%Y-%m-%d %H:%M:%S %z')" "${NC}" "$2" >&2; }
log_info() { log "${GREEN}" "$1"; }
log_err() { log "${RED}" "$1"; }

APP_PATH="${APP_PATH:-/app}"
FPM_HOST="${FPM_HOST:-127.0.0.1}"
FPM_PORT="${FPM_PORT:-9000}"

[ -f "${APP_PATH}/artisan" ] || {
    log_err "应用目录或 artisan 不存在"
    exit 1
}

if ! command -v cgi-fcgi >/dev/null 2>&1; then
    log_err "cgi-fcgi 不存在"
    exit 1
fi

if SCRIPT_NAME=/ping \
    SCRIPT_FILENAME=/ping \
    REQUEST_METHOD=GET \
    cgi-fcgi -bind -connect "${FPM_HOST}:${FPM_PORT}" 2>/dev/null | grep -q 'pong'; then
    log_info "PHP-FPM /ping 正常"
    exit 0
fi

log_err "PHP-FPM /ping 无响应"
exit 1
