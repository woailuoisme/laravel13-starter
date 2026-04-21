<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class DemoTrafficChartWidget extends ChartWidget
{
    protected ?string $pollingInterval = null;

    public function getHeading(): string | Htmlable | null
    {
        return __('dashboard.widgets.chart.heading');
    }

    public function getDescription(): string | Htmlable | null
    {
        return __('dashboard.widgets.chart.description');
    }

    protected function getData(): array
    {
        return [
            'labels' => __('dashboard.widgets.chart.labels'),
            'datasets' => [
                [
                    'label' => __('dashboard.widgets.chart.dataset_label'),
                    'data' => [12, 18, 15, 24, 30, 28, 36, 42, 40, 48, 56, 61],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.18)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
