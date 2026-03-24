# Research: Laravel 13 Native Alternatives & Pest Arch Patterns

## 1. Helper Alternatives (Laravel 13 & PHP 8.5)

### 1.1 JSON Handling
`AppHelper::json_encode` and `json_decode` can be simplified.
- **PHP 8.5**: `json_encode` and `json_decode` already have safe defaults. We should prefer using `Illuminate\Support\Js::from()` for JS-related JSON.
- **mb_trim**: PHP 8.3 引入了 `mb_trim` 系列函数，可以直接替换 `AppHelper::json_decode` 中的手动正则过滤（第 87 行）。

### 1.2 File Size Formatting
- **Standard**: 使用 `Illuminate\Support\Number::fileSize($bytes, precision: 2)`。
- **Benefit**: 移除 `AppHelper::formatFileSize` (Line 137) 和 `AppHelper::readableBytes` (Line 158) 的重复逻辑。

### 1.3 String & Array
- Laravel 11+ 引入了更强大的 `Str` 和 `Arr` helper，项目应确保不自行实现已有的逻辑。

## 2. Exception Handling (Fluent Interface)

Laravel 13 在 `bootstrap/app.php` 中使用 `withExceptions` 闭包。
```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->report(function (Throwable $e) {
        // 自定义上报逻辑 (e.g., AppConfigurator::logException)
    });
    
    $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->expectsJson());
})
```
- **Refactor Goal**: 将 `AppConfigurator::configureExceptions` 内部逻辑平滑迁移至此流式 API。

## 3. Pest Architecture Testing

为了防止 Configurator 模式被误用（例如在 Controller 中调用），我们将引入 `tests/Arch.php`。

### 3.1 核心约束
```php
arch('configurators should only be used in bootstrap or providers')
    ->expect('App\Helpers\Configurators')
    ->toOnlyBeUsedIn([
        'App\Providers',
        'bootstrap\app.php'
    ]);

arch('helpers should not have static state (Octane safety)')
    ->expect('App\Helpers')
    ->not->toHaveStaticProperties();
```

## 4. Model Modernization

### 4.1 Class-based Casts
Laravel 11+ 推荐移除 `$casts` 属性，改为 `casts()` 方法。
```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'options' => AsArrayObject::class, // 代替 'array'
    ];
}
```

### 4.2 Attribute 模式
使用驼峰命名的 Attribute 方法代替旧式的 `getXXXAttribute` / `setXXXAttribute`。
```php
protected function fullName(): Attribute
{
    return Attribute::make(
        get: fn () => "{$this->first_name} {$this->last_name}",
    );
}
```

## 5. Security Refactoring (Secrets)

- **IPInfo Token**: 必须从 `AppHelper::getIpInfo` 移除硬编码 Token，使用 `config('services.ipinfo.token')`。
- **Verification**: 添加一个 `.env.example` 条目以确保部署时不会遗漏。
