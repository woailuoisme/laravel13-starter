#!/bin/sh

# Laravel Schedule 启动脚本
set -e

RED='\033[0;31m' GREEN='\033[0;32m' YELLOW='\033[1;33m' BLUE='\033[0;34m' NC='\033[0m'
log() { printf '%b[%s] [%s]%b %s\n' "$1" "$(date '+%Y-%m-%d %H:%M:%S %z')" "$2" "${NC}" "$3"; }
log_info() { log "${BLUE}" "INFO" "$1"; }
log_success() { log "${GREEN}" "SUCCESS" "$1"; }
log_warning() { log "${YELLOW}" "WARNING" "$1"; }
log_error() { log "${RED}" "ERROR" "$1"; }

APP_PATH="${APP_PATH:-/app}"
APP_ENV="${APP_ENV:-docker}"

# 1. 检查应用目录
check_app_directory() {
    if [ ! -d "${APP_PATH}" ] || [ ! -f "${APP_PATH}/artisan" ]; then
        log_error "应用检查失败: ${APP_PATH}"
        exit 1
    fi

    log_success "应用目录检查通过"
}

start_schedule() {
    log_info "正在直接运行 Laravel Schedule..."
    cd "${APP_PATH}"
    log_info "执行命令: php artisan schedule:work --env=\"${APP_ENV}\""
    exec php artisan schedule:work --env="${APP_ENV}"
}

# 捕获退出信号
trap 'log_warning "接收到终止信号，正在停止 Schedule..."; exit 0' TERM INT

# 主流程
log_info "启动 Laravel Schedule 服务..."
check_app_directory
start_schedule
