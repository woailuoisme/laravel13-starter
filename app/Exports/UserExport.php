<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use OpenSpout\Common\Entity\Style\Style;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Export users with Spatie Simple Excel.
 */
class UserExport
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'nickname',
            'openid',
            'email',
            'createTime',
        ];
    }

    /**
     * @return Builder<User>
     */
    public function query(): Builder
    {
        return User::query()->orderBy('created_at');
    }

    /**
     * @return LazyCollection<int, array<string, string>>
     */
    public function rows(): LazyCollection
    {
        return $this->query()
            ->select(['id', 'nickname', 'open_id', 'email', 'created_at'])
            ->cursor()
            ->map(fn (User $user): array => $this->map($user));
    }

    /**
     * @return array<string, string>
     */
    public function map(User $user): array
    {
        return [
            'nickname' => $user->nickname ?? 'unknown',
            'openid' => $user->open_id ?? '',
            'email' => $user->email ?? '',
            'createTime' => $user->created_at?->toDateTimeString() ?? '',
        ];
    }

    public function writeTo(string $path): string
    {
        $writer = SimpleExcelWriter::create($path)
            ->setHeaderStyle($this->headerStyle())
            ->addHeader($this->headings())
            ->addRows($this->rows());

        $writer->close();

        return $path;
    }

    public function download(string $downloadName = 'users.xlsx'): SimpleExcelWriter
    {
        return SimpleExcelWriter::streamDownload($downloadName)
            ->setHeaderStyle($this->headerStyle())
            ->addHeader($this->headings())
            ->addRows($this->rows());
    }

    private function headerStyle(): Style
    {
        return (new Style())
            ->setFontBold()
            ->setFontSize(12);
    }
}
