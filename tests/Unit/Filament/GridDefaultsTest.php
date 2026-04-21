<?php

declare(strict_types=1);

use Filament\Schemas\Components\Grid;

it('keeps the default filament grid columns intact', function (): void {
    $grid = Grid::make();

    expect($grid->getColumns('lg'))->toBe(2)
        ->and($grid->getColumns('md'))->toBeNull()
        ->and($grid->getColumns('sm'))->toBeNull();
});
