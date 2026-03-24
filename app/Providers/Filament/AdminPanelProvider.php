<?php

namespace App\Providers\Filament;

use App\Helpers\FilamentConfigurator;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return FilamentConfigurator::configure($panel);
    }
}
