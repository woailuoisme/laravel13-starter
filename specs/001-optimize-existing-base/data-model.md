# Data Model Refactoring: User & AdminUser

本项目模型层将全面迁移至 Laravel 13 现代化风格，主要涉及 `User` 和 `AdminUser` 模型。

## 1. User 模型 (app/Models/User.php)

### 1.1 Property-based Casts
移除 `$casts` 属性，采用 `casts()` 方法。
```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'metadata' => 'immutable_array', // 替换 array，确保数据不被意外修改
    ];
}
```

### 1.2 Accessor/Mutator 现代化
移除旧式 `getAttribute` 方法。
```php
protected function avatarUrl(): Attribute
{
    return Attribute::make(
        get: fn ($value) => $value ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->name),
    );
}

protected function initials(): Attribute
{
    return Attribute::make(
        get: fn () => collect(explode(' ', $this->name))
            ->map(fn ($segment) => mb_substr($segment, 0, 1))
            ->join(''),
    );
}
```

## 2. AdminUser 模型 (app/Models/AdminUser.php)

`AdminUser` 与 `User` 类似，但应专注于管理员特定的业务逻辑（如权限管理相关 Attribute）。

### 2.1 强制规范 (Arch Test)
- **Gate**: `AdminUser` 必须是 `FilamentUser` 接口的实现。
- **Gate**: 必须使用 `HasPanelShield` (如果已安装) 或自定义 `canAccessPanel` 方法保证面板安全性。

## 3. 命名规范与类型提示

- **Snake-case**: 数据库字段。
- **Camel-case**: 定义 Attribute (e.g., `fullName`)。
- **Strict Return Types**: 闭包内强制类型提示。

## 4. Relationship Optimization (Eager Loading)

- 在 `AdminUser` 中定义 `reports()` 或 `auditLogs()` 时，必须使用 `HasMany` / `BelongsTo` 等强类型返回声明。
- **Optimization**: 引入 `$with` 属性以减少高频访问属性（如 `role`）的 N+1 问题。
