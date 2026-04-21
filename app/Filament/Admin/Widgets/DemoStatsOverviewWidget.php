<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DemoStatsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make(__('dashboard.widgets.stats.visitors.label'), '18.4k')
                ->description(__('dashboard.widgets.stats.visitors.description'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([8, 10, 9, 12, 14, 15, 18])
                ->color('success'),
            Stat::make(__('dashboard.widgets.stats.signups.label'), '1,248')
                ->description(__('dashboard.widgets.stats.signups.description'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([4, 6, 7, 8, 9, 11, 13])
                ->color('primary'),
            Stat::make(__('dashboard.widgets.stats.conversion_rate.label'), '4.8%')
                ->description(__('dashboard.widgets.stats.conversion_rate.description'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([2, 2, 3, 3, 4, 4, 5])
                ->color('warning'),
        ];
    }
}
