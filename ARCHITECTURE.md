# 项目架构说明 (Architecture Overview)

本文档旨在说明本 Laravel 13 + Filament v5 项目的核心组织结构与设计决策。

## 核心设计模式：配置分离 (Separation of Configuration)

为了保持 `bootstrap/app.php` 和 `PanelProvider` 的整洁，项目采用了 **Configurator** 模式。

### 1. 应用程序入口配置 (`AppConfigurator`)
- **位置**: `app/Helpers/AppConfigurator.php`
- **作用**: 集中处理路由注册、中间件堆栈、异常处理规则以及任务调度配置。
- **集成**: 在 `bootstrap/app.php` 中被调用。

### 2. Filament 后台配置 (`FilamentConfigurator`)
- **位置**: `app/Helpers/FilamentConfigurator.php`
- **作用**: 定义 Panel 的核心设置、插件列表、导航组以及全局搜索逻辑。
- **集成**: 在 `AdminPanelProvider` 中被调用。

### 3. 组件全局默认值 (`ComponentDefaultsProvider`)
- **位置**: `app/Providers/Filament/ComponentDefaultsProvider.php`
- **作用**: 使用 `configureUsing` 钩子为所有 Filament 表单、表格、信息列表组件设置全局默认值（如同对齐方式、日期格式、图片占位符等）。

## 关键目录结构

- `app/Filament/`: 存放所有 Filament 管理后台的 Resource、Page 和 Widget。
- `app/Helpers/`: 存放项目级的 Configurator 和通用工具类。
- `app/Providers/Filament/`: 存放专门针对 Filament 的 Service Providers。
- `tests/`: 使用 **Pest v4** 进行测试驱动开发。

## 技术标准

- **PHP 版本**: 8.5+ (严格类型声明)
- **代码风格**: 遵循 `laravel/pint` (运行 `vendor/bin/pint --dirty`)
- **UI 框架**: Filament v5 (Server-Driven UI), Tailwind CSS v4
- **测试框架**: Pest v4

## 开发流程

1. **生成代码**: 始终使用 `php artisan make:*` 命令。
2. **测试先行**: 使用 `php artisan make:test --pest` 并在实现逻辑前编写测试。
3. **格式化**: 提交前运行 `vendor/bin/pint`。
