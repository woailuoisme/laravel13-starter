<?php

declare(strict_types=1);

use App\Filament\Admin\Widgets\DemoStatsOverviewWidget;
use App\Filament\Admin\Widgets\DemoTrafficChartWidget;
use App\Models\AdminUser;
use Livewire\Livewire;

it('renders the demo dashboard with the demo widgets', function (): void {
    $admin = AdminUser::factory()->create([
        'is_active' => true,
    ]);

    $this->actingAs($admin, 'filament')
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('dashboard.title'));
});

it('renders the demo dashboard widgets', function (): void {
    Livewire::test(DemoStatsOverviewWidget::class)
        ->assertSee(__('dashboard.widgets.stats.visitors.label'))
        ->assertSee(__('dashboard.widgets.stats.signups.label'))
        ->assertSee(__('dashboard.widgets.stats.conversion_rate.label'));

    Livewire::test(DemoTrafficChartWidget::class)
        ->assertSee(__('dashboard.widgets.chart.heading'))
        ->assertSee(__('dashboard.widgets.chart.description'));
});
