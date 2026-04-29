<?php

use App\Exports\UserExport;
use App\Exports\UserImportTemplate;
use App\Imports\UserImport;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

it('exports users with simple excel', function (): void {
    $olderUser = User::factory()->create([
        'name' => 'Alice',
        'nickname' => 'Alice',
        'email' => 'alice@example.com',
        'open_id' => 'openid_alice',
        'created_at' => now()->subDay(),
    ]);

    User::factory()->create([
        'name' => 'Bob',
        'nickname' => null,
        'email' => 'bob@example.com',
        'open_id' => null,
        'created_at' => now(),
    ]);

    $path = storage_path('framework/testing/users-export.xlsx');

    (new UserExport())->writeTo($path);

    $rows = SimpleExcelReader::create($path)->getRows()->all();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['nickname'])->toBe($olderUser->nickname)
        ->and($rows[0]['openid'])->toBe($olderUser->open_id)
        ->and($rows[0]['email'])->toBe($olderUser->email)
        ->and($rows[1]['nickname'])->toBe('unknown');
});

it('exports a user import template with simple excel', function (): void {
    $path = storage_path('framework/testing/user-import-template.xlsx');

    (new UserImportTemplate())->writeTo($path);

    $rows = SimpleExcelReader::create($path)->getRows()->all();

    expect($rows)->toHaveCount(2)
        ->and(array_keys($rows[0]))->toBe(['nickname', 'email', 'openid'])
        ->and($rows[0]['nickname'])->toBe('示例用户1')
        ->and($rows[0]['email'])->toBe('user1@example.com')
        ->and($rows[0]['openid'])->toBe('openid_123');
});

it('imports users with simple excel and upserts by email', function (): void {
    $existingUser = User::factory()->create([
        'name' => 'Existing',
        'nickname' => 'Existing',
        'email' => 'existing@example.com',
        'open_id' => 'old_openid',
    ]);

    $path = storage_path('framework/testing/users-import.xlsx');

    SimpleExcelWriter::create($path)
        ->addRows([
            ['nickname' => ' Updated ', 'email' => ' EXISTING@example.com ', 'openid' => 'new_openid'],
            ['nickname' => 'New User', 'email' => 'new@example.com', 'openid' => ''],
            ['nickname' => ' ', 'email' => ' ', 'openid' => ' '],
        ])
        ->close();

    $result = (new UserImport())->import($path);

    expect($result)->toBe(['imported' => 2, 'skipped' => 1])
        ->and(User::query()->where('email', 'existing@example.com')->value('id'))->toBe($existingUser->id)
        ->and(User::query()->where('email', 'existing@example.com')->value('nickname'))->toBe('Updated')
        ->and(User::query()->where('email', 'existing@example.com')->value('open_id'))->toBe('new_openid')
        ->and(User::query()->where('email', 'new@example.com')->exists())->toBeTrue();
});

it('throws a laravel validation exception for invalid imported rows', function (): void {
    $path = storage_path('framework/testing/users-invalid-import.xlsx');

    SimpleExcelWriter::create($path)
        ->addRow(['nickname' => '', 'email' => 'not-an-email', 'openid' => 'openid'])
        ->close();

    (new UserImport())->import($path);
})->throws(ValidationException::class);
