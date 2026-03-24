<?php

namespace App\Providers\Filament;

use App\Helpers\FilamentConfigurator;
use Filament\Enums\ThemeMode;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class AdminPanelProvider extends PanelProvider
{
    /**
     * @throws \Exception
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->defaultThemeMode(ThemeMode::Dark)
            ->spa(hasPrefetching: true)
            ->id('admin')
            ->path('admin')
            ->brandName(config('app.name'))
            ->unsavedChangesAlerts()
//            ->topbar(false)
            ->passwordReset()
//            ->registration()
//            ->emailVerification()
//            ->emailChangeVerification()
//            ->profile(isSimple: false)
//            ->multiFactorAuthentication([
//                AppAuthentication::make(),
//            ])
//            ->broadcasting(false)
//            ->errorNotifications(false)
            ->registerErrorNotification(
                title: __('filament.notifications.error_title'),
                body: __('filament.notifications.error_body'),
            )
            ->errorNotifications()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->globalSearchDebounce('100ms')
            ->authGuard('filament')
            ->colors([
                'primary' => Color::Amber,
            ])
            // ->simplePageMaxContentWidth(Width::Small)
            ->sidebarWidth('14rem')
            ->sidebarCollapsibleOnDesktop()
            ->collapsedSidebarWidth('6rem')
            ->collapsibleNavigationGroups(true)
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->pages(FilamentConfigurator::getPages())
            ->widgets(FilamentConfigurator::getWidgets())
            ->userMenuItems(FilamentConfigurator::getUserMenuItems())
            ->navigationGroups(FilamentConfigurator::getNavigationGroups())
            ->middleware(FilamentConfigurator::getMiddleware())
            ->plugins(FilamentConfigurator::getPlugins())
            ->authMiddleware(FilamentConfigurator::getAuthMiddleware());
    }
}
