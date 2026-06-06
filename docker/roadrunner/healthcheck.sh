#!/bin/sh

# Laravel 应用角色健康检查脚本
set -e

# 日志工具
RED='\033[0;31m' GREEN='\033[0;32m' NC='\033[0m'
log() { printf '%b[%s] [HEALTH]%b %s\n' "$1" "$(date '+%Y-%m-%d %H:%M:%S %z')" "${NC}" "$2" >&2; }
log_info() { log "${GREEN}" "$1"; }
log_err() { log "${RED}" "$1"; }

APP_PATH="${APP_PATH:-/app}"
ROLE="${1:-}"

# 1. 检查应用目录
[ -f "${APP_PATH}/artisan" ] || {
    log_err "应用目录或 artisan 不存在"
    exit 1
}

cd "${APP_PATH}"

# 2. 检查进程 (通过 /proc 检查，避开自身)
has_process() {
    pattern="${1}"

    for pid_dir in /proc/[0-9]*/; do
        [ "${pid_dir}" = "/proc/$$/" ] && continue
        [ -r "${pid_dir}cmdline" ] || continue

        cmdline="$(tr '\000' ' ' <"${pid_dir}cmdline")"

        if printf '%s\n' "${cmdline}" | grep -Fq "${pattern}"; then
            return 0
        fi
    done

    return 1
}

check_octane() {
    if ! has_process "artisan octane:start " && ! has_process "rr serve "; then
        log_err "RoadRunner/Octane 进程异常"
        exit 1
    fi

    if curl -f -s -m 5 "http://localhost:${APP_PORT:-8001}/up" >/dev/null 2>&1; then
        log_info "RoadRunner/HTTP 服务正常"
        exit 0
    fi

    log_err "HTTP 服务无响应"
    exit 1
}

check_horizon() {
    if ! has_process "artisan horizon "; then
        log_err "Horizon 进程异常"
        exit 1
    fi

    if php artisan horizon:status --no-interaction >/dev/null 2>&1; then
        log_info "Horizon 服务正常"
        exit 0
    fi

    log_err "Horizon 状态异常"
    exit 1
}

check_schedule() {
    if has_process "artisan schedule:work "; then
        log_info "Schedule 服务正常"
        exit 0
    fi

    log_err "Schedule 进程异常"
    exit 1
}

case "${ROLE}" in
octane)
    check_octane
    ;;
horizon)
    check_horizon
    ;;
schedule)
    check_schedule
    ;;
*)
    log_err "健康检查角色无效: ${ROLE:-未指定}"
    exit 1
    ;;
esac
