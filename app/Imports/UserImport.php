<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Import users with Spatie Simple Excel.
 */
class UserImport
{
    private const int BATCH_SIZE = 1000;

    /**
     * @return array{imported: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function import(string $path): array
    {
        $imported = 0;
        $skipped = 0;

        $this->rows($path)
            ->chunk(self::BATCH_SIZE)
            ->each(function (LazyCollection $chunk) use (&$imported, &$skipped): void {
                $records = [];
                $errors = [];

                foreach ($chunk as $rowNumber => $row) {
                    if ($this->isEmptyRow($row)) {
                        $skipped++;

                        continue;
                    }

                    $validator = Validator::make($row, $this->rules(), $this->messages(), $this->attributes());

                    if ($validator->fails()) {
                        foreach ($validator->errors()->messages() as $attribute => $messages) {
                            $errors["rows.{$rowNumber}.{$attribute}"] = $messages;
                        }

                        continue;
                    }

                    $records[] = $this->recordFromRow($row);
                }

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                if ($records === []) {
                    return;
                }

                User::query()->upsert(
                    $records,
                    ['email'],
                    ['name', 'nickname', 'open_id', 'updated_at'],
                );

                $imported += count($records);
            });

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function rows(string $path): LazyCollection
    {
        return SimpleExcelReader::create($path)
            ->preserveEmptyRows()
            ->trimHeaderRow()
            ->formatHeadersUsing(fn (string $header): string => $this->normalizeHeader($header))
            ->getRows()
            ->values()
            ->mapWithKeys(fn (array $row, int $index): array => [$index + 2 => $this->normalizeRow($row)]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'openid' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.required' => '用户名不能为空',
            'nickname.string' => '用户名必须是字符串',
            'nickname.max' => '用户名不能超过255个字符',
            'email.required' => '邮箱不能为空',
            'email.email' => '邮箱格式不正确',
            'email.max' => '邮箱不能超过255个字符',
            'openid.string' => 'OpenID必须是字符串',
            'openid.max' => 'OpenID不能超过255个字符',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nickname' => '用户名',
            'email' => '邮箱',
            'openid' => 'OpenID',
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function isEmptyRow(array $row): bool
    {
        return mb_trim((string) ($row['nickname'] ?? '')) === ''
            && mb_trim((string) ($row['email'] ?? '')) === ''
            && mb_trim((string) ($row['openid'] ?? '')) === '';
    }

    private function normalizeHeader(string $header): string
    {
        return match (Str::of($header)->trim()->lower()->replace([' ', '-', '_'], '')->toString()) {
            '用户名' => 'nickname',
            'openid' => 'openid',
            default => Str::of($header)->trim()->snake()->toString(),
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'nickname' => mb_trim((string) ($row['nickname'] ?? $row['用户名'] ?? '')),
            'email' => Str::of((string) ($row['email'] ?? ''))->trim()->lower()->toString(),
            'openid' => mb_trim((string) ($row['openid'] ?? $row['open_id'] ?? $row['OpenId'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function recordFromRow(array $row): array
    {
        $now = now();
        $nickname = (string) $row['nickname'];

        return [
            'name' => $nickname,
            'nickname' => $nickname,
            'email' => (string) $row['email'],
            'password' => Hash::make(Str::password(32)),
            'open_id' => $row['openid'] !== '' ? (string) $row['openid'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
