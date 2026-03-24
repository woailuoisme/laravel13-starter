<?php

declare(strict_types=1);

arch('configurators should only be used in bootstrap or providers')
    ->expect('App\Helpers\AppConfigurator')
    ->toOnlyBeUsedIn([
        'App\Providers',
        'bootstrap',
    ]);

arch('filament configurator should only be used in providers')
    ->expect('App\Helpers\FilamentConfigurator')
    ->toOnlyBeUsedIn([
        'App\Providers',
    ]);
