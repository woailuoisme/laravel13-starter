<?php

declare(strict_types=1);

namespace App\Exports;

use OpenSpout\Common\Entity\Style\Style;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Export the user import template with Spatie Simple Excel.
 */
class UserImportTemplate
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'nickname',
            'email',
            'openid',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function rows(): array
    {
        return [
            [
                'nickname' => '示例用户1',
                'email' => 'user1@example.com',
                'openid' => 'openid_123',
            ],
            [
                'nickname' => '示例用户2',
                'email' => 'user2@example.com',
                'openid' => 'openid_456',
            ],
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

    public function download(string $downloadName = 'user-import-template.xlsx'): SimpleExcelWriter
    {
        return SimpleExcelWriter::streamDownload($downloadName)
            ->setHeaderStyle($this->headerStyle())
            ->addHeader($this->headings())
            ->addRows($this->rows());
    }

    private function headerStyle(): Style
    {
        return (new Style())->setFontBold();
    }
}
