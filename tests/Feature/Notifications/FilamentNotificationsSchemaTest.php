<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('stores filament notifications data as jsonb on postgres', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('This schema assertion only applies to PostgreSQL.');
    }

    $columnType = DB::selectOne(
        "select data_type from information_schema.columns where table_name = 'notifications' and column_name = 'data'",
    );

    expect($columnType?->data_type)->toBe('jsonb');
});
