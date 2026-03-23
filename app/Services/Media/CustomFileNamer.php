<?php

namespace App\Services\Media;

use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Support\FileNamer\FileNamer;

/**
 * 自定义文件命名器
 *
 * 使用 MD5 哈希值作为文件名，确保文件名唯一性和去重复
 */
class CustomFileNamer extends FileNamer
{
    /**
     * 生成原始文件名
     */
    public function originalFileName(string $fileName): string
    {
        // 获取文件扩展名
        $extension = mb_strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // 生成 MD5 哈希（基于文件内容）
        // 注意：这里我们无法直接访问文件内容，所以使用时间戳和随机数
        // 实际的 MD5 哈希会在 MediaService 中通过 usingFileName 设置
        //        $hash = md5($fileName . microtime(true) . rand(1000, 9999));
        $hash = md5($fileName);

        return $extension ? "{$hash}.{$extension}" : $hash;
    }

    /**
     * 生成转换文件名
     */
    public function conversionFileName(string $fileName, Conversion $conversion): string
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        return "{$baseName}-{$conversion->getName()}.{$extension}";
    }

    /**
     * 生成响应式图片文件名
     */
    public function responsiveFileName(string $fileName): string
    {
        return $fileName;
    }
}
