<?php

namespace App\Services\Media;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomMediaPathGenerator implements PathGenerator
{
    /**
     * 获取媒体文件存储路径
     */
    public function getPath(Media $media): string
    {
        // 获取模型名称并转换为蛇形命名的复数形式路径
        $modelClass = $media->model_type;
        $modelName = class_basename($modelClass);
        $pluralPath = Str::plural(Str::snake($modelName));

        // 获取 collection 名称
        $collectionName = $media->collection_name ?: 'default';

        return $pluralPath.'/'.$media->model_id.'/'.$collectionName.'/';
    }

    /**
     * 获取转换后的媒体文件存储路径
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    /**
     * 获取响应式图片的存储路径
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }
}
