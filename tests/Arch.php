<?php

declare(strict_types=1);

arch('app uses strict types')->expect('App')->toUseStrictTypes();

arch('app does not use debugging statements')->expect(['dd', 'dump', 'ray', 'var_dump'])->not->toBeUsed();

arch('models extend eloquent model')->expect('App\Models')->toExtend('Illuminate\Database\Eloquent\Model');

arch('enums are enums')->expect('App\Enums')->toBeEnums();

arch('requests extend form request')->expect('App\Http\Requests')->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('controllers have controller suffix')->expect('App\Http\Controllers')->toHaveSuffix('Controller');

arch('commands extend command')->expect('App\Console\Commands')->toExtend('Illuminate\Console\Command');

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

arch('support configuration should only be used in bootstrap, providers, or the configurator facade')
    ->expect('App\Support\Configuration')
    ->toOnlyBeUsedIn([
        'App\Providers',
        'App\Helpers\AppConfigurator',
        'bootstrap',
    ]);
