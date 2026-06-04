#!/usr/bin/env bash

set -euo pipefail

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}=== PHP 扩展安装脚本 (基于 PIE) ===${NC}"

# 1. 检查操作系统
if [[ "${OSTYPE}" != "darwin"* ]]; then
    echo -e "${RED}错误: 此脚本目前仅针对 macOS (Homebrew 环境) 进行优化。${NC}"
    exit 1
fi

# 2. 检查编译依赖工具
echo -e "${BLUE}[1/5] 检查系统编译开发工具...${NC}"
MISSING_TOOLS=()
for tool in autoconf pkg-config make; do
    if ! command -v "${tool}" &> /dev/null; then
        MISSING_TOOLS+=("${tool}")
    fi
done

if [ ${#MISSING_TOOLS[@]} -ne 0 ]; then
    echo -e "${YELLOW}发现缺失的编译工具: ${MISSING_TOOLS[*]}${NC}"
    if command -v brew &> /dev/null; then
        echo -e "${BLUE}正在通过 Homebrew 安装缺失的工具...${NC}"
        brew install "${MISSING_TOOLS[@]}"
    else
        echo -e "${RED}错误: 未检测到 brew，请手动安装以下编译工具: ${MISSING_TOOLS[*]}${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}系统编译工具检测通过。${NC}"
fi

# 3. 检测 PHP 及其 conf.d 目录
echo -e "${BLUE}[2/5] 检测本地 PHP 配置...${NC}"
if ! command -v php &> /dev/null; then
    echo -e "${RED}错误: 未在 PATH 中检测到 php 可执行文件。${NC}"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo -e "当前 PHP 版本: ${GREEN}${PHP_VERSION}${NC}"

# 获取 php.ini 扫描目录 (conf.d)
INI_DIR=$(php-config --ini-dir 2>/dev/null || php -i | grep "Scan this dir for additional .ini files" | cut -d" " -f9)

if [ -z "${INI_DIR}" ] || [ ! -d "${INI_DIR}" ]; then
    # 回退到根据 Homebrew 的标准 PHP 路径进行猜测
    INI_DIR="/opt/homebrew/etc/php/${PHP_VERSION}/conf.d"
fi

echo -e "PHP 配置扫描目录 (conf.d): ${GREEN}${INI_DIR}${NC}"
if [ ! -w "${INI_DIR}" ]; then
    echo -e "${YELLOW}警告: 目录 ${INI_DIR} 对当前用户没有写权限，配置写入时可能会提示输入密码。${NC}"
fi

# 4. 下载 PIE (PHP Extension Installer)
echo -e "${BLUE}[3/5] 下载 PIE 工具...${NC}"
TEMP_DIR=$(mktemp -d)
trap 'rm -rf "${TEMP_DIR}"' EXIT

PIE_PHAR="${TEMP_DIR}/pie.phar"
PIE_URL="https://github.com/php/pie/releases/latest/download/pie.phar"

echo -e "正在从 GitHub 下载最新版 PIE..."
if curl -L -sS --fail "${PIE_URL}" -o "${PIE_PHAR}"; then
    chmod +x "${PIE_PHAR}"
    echo -e "${GREEN}PIE 工具下载成功。${NC}"
else
    echo -e "${RED}错误: 下载 PIE 失败，请检查网络连接或手动从 ${PIE_URL} 下载。${NC}"
    exit 1
fi

# 5. 编译安装扩展
PIE_PACKAGES=(
    "pie-extensions/igbinary"
    "msgpack/msgpack-php"
    "pie-extensions/redis"
)
EXT_NAMES=(
    "igbinary"
    "msgpack"
    "redis"
)
INSTALL_OPTS=(
    ""
    ""
    "--enable-redis-igbinary --enable-redis-msgpack"
)

echo -e "${BLUE}[4/5] 开始安装扩展...${NC}"

for i in "${!PIE_PACKAGES[@]}"; do
    pkg="${PIE_PACKAGES[${i}]}"
    ext="${EXT_NAMES[${i}]}"
    opts="${INSTALL_OPTS[${i}]}"
    
    echo -e "\n${BLUE}正在安装扩展包: ${pkg}...${NC}"
    
    # 运行 PIE 安装扩展，需要小心处理附加配置选项的展开
    # shellcheck disable=SC2086
    if [ -n "${opts}" ]; then
        if php "${PIE_PHAR}" install "${pkg}" ${opts} --no-interaction; then
            echo -e "${GREEN}扩展包 ${pkg} (${ext}) 编译安装成功。${NC}"
        else
            echo -e "${RED}错误: 扩展包 ${pkg} (${ext}) 安装失败，请检查上面编译输出错误信息。${NC}"
        fi
    else
        if php "${PIE_PHAR}" install "${pkg}" --no-interaction; then
            echo -e "${GREEN}扩展包 ${pkg} (${ext}) 编译安装成功。${NC}"
        else
            echo -e "${RED}错误: 扩展包 ${pkg} (${ext}) 安装失败，请检查上面编译输出错误信息。${NC}"
        fi
    fi
done

# 6. 配置加载启用扩展
echo -e "${BLUE}[5/5] 配置并启用扩展加载...${NC}"

write_ini_config() {
    local ext_name=$1
    local ini_file="${INI_DIR}/50-${ext_name}.ini"
    
    if [ -f "${ini_file}" ]; then
        echo -e "${YELLOW}配置文件 ${ini_file} 已存在，跳过写入。${NC}"
        return
    fi
    
    echo -e "正在写入配置到 ${ini_file}..."
    
    # 尝试写入，用 success 标识记录命令的执行结果以代替对 $? 的判断
    local success=true
    if [ -w "${INI_DIR}" ]; then
        echo "extension=${ext_name}.so" > "${ini_file}" || success=false
    else
        echo "extension=${ext_name}.so" | sudo tee "${ini_file}" > /dev/null || success=false
    fi
    
    if [ "${success}" = true ]; then
        echo -e "${GREEN}扩展 ${ext_name} 配置文件创建成功。${NC}"
    else
        echo -e "${RED}错误: 无法写入配置文件 ${ini_file}。${NC}"
    fi
}

for ext in "${EXT_NAMES[@]}"; do
    # 检测扩展是否已经加载
    if php -m | grep -ri "^${ext}$" &>/dev/null; then
        echo -e "${GREEN}扩展 ${ext} 已经加载，无需配置。${NC}"
    else
        write_ini_config "${ext}"
    fi
done

echo -e "\n${GREEN}=== 所有操作完成！===${NC}"
echo -e "${YELLOW}请重启您的 PHP 运行服务（如 php-fpm, octane, roadrunner 等）以使配置生效。${NC}"
