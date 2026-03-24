<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\FilamentNavigationGroup;
use Awcodes\QuickCreate\QuickCreatePlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Filament 后台配置助手类 (最佳实践)
 *
 * 通过助手类解耦 AdminPanelProvider 的配置逻辑，提高可维护性。
 * 遵循 Filament v13 与 Laravel 13 的最佳实践。
 */
class FilamentConfigurator
{
    /**
     * 配置 Panel 的核心方法
     */
    public static function configure(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile() // 启用默认个人中心
            ->spa() // 启用 SPA 模式提升体验
            ->unsavedChangesAlerts() // 启用未保存内容提示
            ->databaseTransactions() // 启用数据库事务自动管理
            ->brandName('Laravel Admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'danger' => Color::Rose,
                'warning' => Color::Orange,
                'info' => Color::Sky,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->navigationGroups(self::getNavigationGroups())
            ->plugins(self::getPlugins())
            ->middleware(self::getMiddleware())
            ->authMiddleware(self::getAuthMiddleware())
            ->font('Inter') // 设置全球主流字体
            ->maxContentWidth(Width::Full); // 默认内容宽度设为全屏优化
    }

    /**
     * 获取所有导航组配置
     * 使用 Enum 管理导航组，保证排序与图标一致。
     *
     * @return array<NavigationGroup>
     */
    public static function getNavigationGroups(): array
    {
        return array_map(
            static fn (FilamentNavigationGroup $group): NavigationGroup => NavigationGroup::make()
                ->label($group->getLabel())
                ->icon($group->getIcon())
                ->collapsible()
                ->collapsed(),
            FilamentNavigationGroup::cases(),
        );
    }

    /**
     * 获取已注册的 Filament 插件
     *
     * @return array<mixed>
     */
    public static function getPlugins(): array
    {
        return [
            self::buildQuickCreatePlugin(),
            self::buildShieldPlugin(),
        ];
    }

    /**
     * 获取标准中间件列表
     * 已针对 Laravel 13 优化，并移除了过时引用。
     *
     * @return array<class-string>
     */
    public static function getMiddleware(): array
    {
        return [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ];
    }

    /**
     * 获取认证中间件
     *
     * @return array<class-string>
     */
    public static function getAuthMiddleware(): array
    {
        return [Authenticate::class];
    }

    /**
     * 构建快速创建插件
     */
    private static function buildQuickCreatePlugin(): QuickCreatePlugin
    {
        return QuickCreatePlugin::make()
            ->label('快速添加')
            ->rounded(true)
            ->slideOver()
            ->keyBindings(['command+shift+a', 'ctrl+shift+a'])
            ->createAnother(true)
            ->sortBy('navigation');
    }

    /**
     * 构建权限管理插件 (Shield)
     */
    private static function buildShieldPlugin(): FilamentShieldPlugin
    {
        return FilamentShieldPlugin::make()
            ->gridColumns(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->sectionColumnSpan(1)
            ->checkboxListColumns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->resourceCheckboxListColumns(['default' => 1, 'sm' => 2])
            ->globallySearchable(false);
    }
}
