<?php

declare(strict_types=1);

namespace App\Services\Media;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

class CustomMediaUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        $disk = $this->media->disk;
        $diskConfig = config("filesystems.disks.{$disk}");

        // 如果是 OSS 磁盘且配置了自定义 URL
        if ($disk === 'oss' && is_string($diskConfig['url'] ?? null) && $diskConfig['url'] !== '') {
            $path = $this->getPathRelativeToRoot();

            return mb_rtrim($diskConfig['url'], '/').'/'.mb_ltrim($path, '/');
        }

        // 其他情况使用默认逻辑
        return parent::getUrl();
    }
}
