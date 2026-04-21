<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\DemoStatsOverviewWidget;
use App\Filament\Admin\Widgets\DemoTrafficChartWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?int $navigationSort = -1000;

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation.label');
    }

    public function getTitle(): string
    {
        return __('dashboard.title');
    }

    public function getWidgets(): array
    {
        return [
            DemoStatsOverviewWidget::class,
            DemoTrafficChartWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
