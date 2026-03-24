# Quickstart: Project Optimization Tests

本阶段优化的核心目标是建立架构防护网并确保基础助手逻辑的稳健。

## 1. 运行现有测试

首先确保现有代码库的基础状态正常：
```bash
php artisan test --compact
```

## 2. 运行架构测试 (Arch Testing)

重构后，请确保系统满足预定义的架构红线：
```bash
# 运行 Arch 测试
php artisan test --compact --filter=Arch
```

## 3. 运行助手类专题测试

针对 `AppHelper` 和 `AppConfigurator` 的专有测试：
```bash
php artisan test --compact --filter=Helper
```

## 4. 运行模型层专题测试

确认现代化重构后的属性转换逻辑：
```bash
php artisan test --compact --filter=Model
```

## 5. 安全扫描与代码风格检查

在提交前运行以下脚本：
```bash
# 代码风格检查 (自动修复)
vendor/bin/pint --dirty

# 静态分析 (PHPStan Level 6+)
./vendor/bin/phpstan analyze app/Models app/Helpers
```

## 6. 环境确认 (Pest Architecture Required)

确保已安装 `pestphp/pest-plugin-arch`：
```bash
composer show pestphp/pest-plugin-arch
```
如果未安装，请在执行任务 1 前运行：
```bash
composer require pestphp/pest-plugin-arch --dev
```
