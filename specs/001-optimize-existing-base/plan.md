# Implementation Plan: Project "High-Weight" Optimization (Base System)

**Branch**: `001-optimize-existing-base` | **Date**: 2026-03-24 | **Spec**: [spec.md](file:///Users/seaside/Projects/laravel/laravel13_starter/specs/001-optimize-existing-base/spec.md)
**Input**: Feature specification from `/specs/001-optimize-existing-base/spec.md`

## Summary

本项目旨在通过针对性重构提升系统稳健性、安全性与代码现代化水平。核心任务包括：
1.  **安全性升级**：将 `AppHelper` 中的硬编码秘钥（如 IPInfo Token）迁移至环境配置，消除泄露风险。
2.  **代码现代化**：利用 Laravel 11/13 特性（类属性 Casts、Attribute 模式、Mbstring 原生支持）重构模型层与助手类。
3.  **测试驱动治理**：将测试数量从 10 个提升至 40 个以上，引入 **Pest Architecture Testing** 强制执行 Configurator 解耦模式，确保长期架构不腐化。
4.  **冗余清理**：合并助手类中重复的文件处理逻辑，清理 Octane 环境下不安全的静态状态。

## Technical Context

**Language/Version**: PHP 8.5, Laravel 13.0+  
**Primary Dependencies**: Filament v5, Pest v4, Octane (RoadRunner), Meilisearch, Redis  
**Storage**: PostgreSQL (Primary DB), Redis (Cache/Queue), MinIO (Object Storage)  
**Testing**: Pest v4 (Unit, Feature, Architecture)  
**Target Platform**: Optimized for Octane (High Concurrency)  
**Project Type**: Monolithic Laravel Application with Filament SDUI  
**Performance Goals**: <5ms overhead for AppHelper calls; Request-safe singletons  
**Constraints**: Zero downtime refactoring; Strict type enforcement (PHP 8.5)  
**Scale/Scope**: Core Infrastructure Helper, Models (User, AdminUser), Exception Handler

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Gate | Requirement | Status |
|------|-------------|--------|
| **Laravel-First** | 必须使用 Laravel 13 的 fluent 异常处理与最新的属性定义语法。 | ✅ 已对齐 |
| **Filament v5** | 组件默认值需由 `ComponentDefaultsProvider` 统一驱动。 | ✅ 已对齐 |
| **AI-Native** | 所有重构必须保持强类型声明，便于 AI 代理解析。 | ✅ 已对齐 |
| **Pest TDD** | 重构前必须建立架构基准测试（Arch Test）。 | ✅ 计划中 |
| **Modern PHP** | 强制使用 PHP 8.5 特性（如 Property Promotion）。 | ✅ 已对齐 |

## Project Structure

### Documentation (this feature)

```text
specs/001-optimize-existing-base/
├── plan.md              # 本计划文件
├── research.md          # 针对 Laravel 13 原生替代方案的研究
├── data-model.md        # 模型重构蓝图 (Casts/Attributes)
├── quickstart.md        # 测试运行指引
└── tasks.md             # 详细任务清单 (待执行 /speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Helpers/
│   ├── AppHelper.php        # 逻辑清理与功能合并
│   └── AppConfigurator.php   # 异常处理流式重构
├── Models/
│   ├── User.php             # 引入类属性 Casts 与 Attribute
│   └── AdminUser.php        # 同上
├── Providers/
│   └── Filament/
│       └── ComponentDefaultsProvider.php # 细节优化

tests/
├── Arch.php                 # 新增：架构规范测试
├── Feature/
│   └── Helpers/             # 新增：助手类功能测试
└── Unit/
    └── Models/              # 新增：模型逻辑测试
```

**Structure Decision**: 采用标准的单体项目结构，在 `tests/` 目录下新增 `Arch.php` 以确立架构红线，模型层进行原地现代化改造。

## Complexity Tracking

> **无宪法冲突，无需 justify 复杂性。**
