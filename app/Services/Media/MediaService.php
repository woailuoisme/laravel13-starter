<?php

namespace App\Services\Media;

use App\Helpers\AppHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Exceptions\UnreachableUrl;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaService
{
    /**
     * 静态实例
     */
    private static ?self $instance = null;

    /**
     * 支持的图片格式
     */
    public const array SUPPORTED_IMAGE_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    /**
     * 支持的视频格式
     */
    public const array SUPPORTED_VIDEO_TYPES = [
        'video/mp4',
        'video/avi',
        'video/mov',
        'video/wmv',
        'video/webm',
    ];

    /**
     * 公共构造函数，支持控制反转 (DI)
     */
    public function __construct()
    {
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 验证文件类型
     */
    public function validateFileType(UploadedFile $file, ?array $allowedTypes = null): bool
    {
        $allowedTypes = $allowedTypes ?? array_merge(self::SUPPORTED_IMAGE_TYPES, self::SUPPORTED_VIDEO_TYPES);

        return in_array($file->getMimeType(), $allowedTypes, true);
    }

    /**
     * 检查文件是否为图片
     */
    public function isImage(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), self::SUPPORTED_IMAGE_TYPES, true);
    }

    /**
     * 检查文件是否为视频
     */
    public function isVideo(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), self::SUPPORTED_VIDEO_TYPES, true);
    }

    /**
     * 生成标准化的文件名（MD5 + 原始扩展名）
     */
    public function generateFileName(UploadedFile $file): string
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $md5Hash = md5_file($file->getRealPath());

        return $extension ? "{$md5Hash}.{$extension}" : $md5Hash;
    }

    /**
     * 生成模型对应的存储路径
     */
    public function generateStoragePath(HasMedia $model, string $subPath = ''): string
    {
        $className = class_basename($model);
        $pluralPath = Str::plural(mb_strtolower($className));

        // 特殊处理一些模型的复数形式
        $specialCases = [
            'feedback' => 'feedbacks',
            'category' => 'categories',
            'person' => 'people',
        ];

        if (isset($specialCases[$pluralPath])) {
            $pluralPath = $specialCases[$pluralPath];
        }

        return $subPath ? "{$pluralPath}/{$subPath}/" : "{$pluralPath}/";
    }

    /**
     * 上传单个文件到指定集合
     *
     * @param HasMedia $model 模型实例
     * @param UploadedFile $file 上传文件
     * @param string $collection 集合名称
     * @param array $customProperties 自定义属性
     *
     * @throws FileDoesNotExist|FileIsTooBig
     */
    public function uploadSingle(HasMedia $model, UploadedFile $file, string $collection = 'default', array $customProperties = []): Media
    {
        // 验证文件
        if (! $file->isValid()) {
            throw new InvalidArgumentException('上传文件无效: '.$file->getErrorMessage());
        }

        // 生成基于文件内容的 MD5 哈希文件名
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $md5Hash = md5_file($file->getRealPath());
        $fileName = $extension ? "{$md5Hash}.{$extension}" : $md5Hash;

        // 检查是否已存在相同文件（去重复）
        $existingMedia = $this->findExistingMedia($model, $fileName, $collection);
        if ($existingMedia) {
            \Log::info('文件已存在，返回现有媒体记录', [
                'model' => get_class($model),
                'model_id' => $model->id ?? 'new',
                'collection' => $collection,
                'file_name' => $fileName,
                'existing_media_id' => $existingMedia->id,
            ]);

            return $existingMedia;
        }

        try {
            return $model->addMedia($file)
                ->usingFileName($fileName)        // 设置实际存储的文件名
                ->usingName($file->getClientOriginalName()) // 使用原始文件名作为显示名称
                ->withCustomProperties(array_merge([
                    'original_name' => $file->getClientOriginalName(),
                    'file_hash' => $md5Hash,
                    'file_size' => $file->getSize(),
                    'upload_time' => now()->toISOString(),
                ], $customProperties))
                ->toMediaCollection($collection);
        } catch (\Exception $e) {
            \Log::error('文件上传失败', [
                'model' => get_class($model),
                'model_id' => $model->id ?? 'new',
                'collection' => $collection,
                'file_name' => $fileName,
                'original_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 查找已存在的相同文件（基于文件名）
     */
    protected function findExistingMedia(HasMedia $model, string $fileName, string $collection): ?callable
    {
        return $model->getMedia($collection)->where('file_name', $fileName)->first();
    }

    /**
     * 上传多个文件到指定集合
     *
     * @param HasMedia $model 模型实例
     * @param array<UploadedFile> $files 上传文件数组
     * @param string $collection 集合名称
     * @param bool $preserveOrder 是否保持排序
     * @return Collection<Media>
     *
     * @throws FileDoesNotExist|FileIsTooBig
     */
    public function uploadMultiple(HasMedia $model, array $files, string $collection = 'images', bool $preserveOrder = true): Collection
    {
        $uploadedMedia = collect();
        $maxOrder = $preserveOrder ? ($model->getMedia($collection)->max('order_column') ?? 0) : 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                Log::warning('跳过非 UploadedFile 对象', ['file' => $file]);

                continue;
            }
            $media = $this->uploadSingle($model, $file, $collection);

            if ($preserveOrder) {
                $maxOrder++;
                $media->update(['order_column' => $maxOrder]);
            }

            $uploadedMedia->push($media);

        }

        return $uploadedMedia;
    }

    /**
     * 处理缩略图上传（兼容旧方法）
     *
     * @throws FileDoesNotExist|FileIsTooBig
     */
    public function handleThumbUpload(HasMedia $model, UploadedFile $file, string $collection = 'thumb'): Media
    {
        return $this->uploadSingle($model, $file, $collection);
    }

    /**
     * 处理多图上传（兼容旧方法）
     *
     * @throws FileDoesNotExist|FileIsTooBig
     */
    public function handleImagesUpload(HasMedia $model, array $files, string $collection = 'images'): void
    {
        $this->uploadMultiple($model, $files, $collection);
    }

    /**
     * 验证上传文件
     */
    private function validateUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('上传文件无效: '.$file->getErrorMessage());
        }

        if (! $this->validateFileType($file)) {
            throw new InvalidArgumentException('不支持的文件类型: '.$file->getMimeType());
        }
    }

    /**
     * 获取清理后的文件名（去除路径信息）
     */
    private function getCleanFileName(UploadedFile $file): string
    {
        return pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    }

    /**
     * 根据 ID 批量删除媒体文件
     *
     * @param HasMedia $model 模型实例
     * @param array<int> $mediaIds 媒体 ID 数组
     * @param string $collection 集合名称
     * @return int 删除成功的文件数量
     */
    public function deleteByIds(HasMedia $model, array $mediaIds, string $collection = ''): int
    {
        $deletedCount = 0;

        foreach ($mediaIds as $id) {
            $query = $model->getMedia($collection);
            $media = $query->find($id);

            if ($media) {
                try {
                    $media->delete();
                    $deletedCount++;
                } catch (\Exception $e) {
                    Log::error('删除媒体文件失败', [
                        'media_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $deletedCount;
    }

    /**
     * 更新媒体文件排序
     *
     * @param HasMedia $model 模型实例
     * @param array<int, int> $orderMap 媒体 ID => 排序位置的映射
     * @param string $collection 集合名称
     * @return int 更新成功的文件数量
     */
    public function updateOrder(HasMedia $model, array $orderMap, string $collection = 'images'): int
    {
        $updatedCount = 0;

        foreach ($orderMap as $mediaId => $position) {
            $media = $model->getMedia($collection)->find($mediaId);

            if ($media) {
                try {
                    $media->update(['order_column' => $position]);
                    $updatedCount++;
                } catch (\Exception $e) {
                    Log::error('更新媒体文件排序失败', [
                        'media_id' => $mediaId,
                        'position' => $position,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $updatedCount;
    }

    /**
     * 获取媒体文件信息
     *
     * @param HasMedia $model 模型实例
     * @param string $collection 集合名称
     * @param bool $withUrls 是否包含 URL
     * @return array 媒体文件信息数组
     */
    public function getMediaInfo(HasMedia $model, string $collection = '', bool $withUrls = true): array
    {
        $media = $collection ? $model->getMedia($collection) : $model->getMedia();

        return $media->map(function (Media $item) use ($withUrls) {
            $info = [
                'id' => $item->id,
                'name' => $item->name,
                'file_name' => $item->file_name,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'human_readable_size' => AppHelper::formatFileSize($item->size),
                'collection_name' => $item->collection_name,
                'order_column' => $item->order_column,
                'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                'custom_properties' => $item->custom_properties,
            ];

            if ($withUrls) {
                $info['url'] = $item->getUrl();
                $info['full_url'] = $item->getFullUrl();
            }

            return $info;
        })->all();
    }

    /**
     * 替换媒体文件（删除旧文件，上传新文件）
     *
     * @param HasMedia $model 模型实例
     * @param UploadedFile $newFile 新文件
     * @param string $collection 集合名称
     * @param int|null $replaceMediaId 要替换的媒体 ID（为 null 则替换集合中的第一个）
     */
    public function replaceMedia(HasMedia $model, UploadedFile $newFile, string $collection = 'default', ?int $replaceMediaId = null): ?Media
    {
        try {
            // 获取要替换的媒体
            if ($replaceMediaId) {
                $oldMedia = $model->getMedia($collection)->find($replaceMediaId);
            } else {
                $oldMedia = $model->getMedia($collection)->first();
            }

            $oldOrder = $oldMedia?->order_column;
            $oldCustomProperties = $oldMedia?->custom_properties ?? [];

            // 上传新文件
            $newMedia = $this->uploadSingle($model, $newFile, $collection, $oldCustomProperties);

            // 保持原有的排序
            if ($oldOrder !== null) {
                $newMedia->update(['order_column' => $oldOrder]);
            }

            // 删除旧文件
            $oldMedia?->delete();

            return $newMedia;
        } catch (\Exception $e) {
            Log::error('替换媒体文件失败', [
                'collection' => $collection,
                'replace_media_id' => $replaceMediaId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 清除媒体集合
     */
    public function clearMediaCollection(HasMedia $model, string $collection): bool
    {
        try {
            $model->clearMediaCollection($collection);

            return true;
        } catch (\Exception $e) {
            Log::error('清除媒体集合失败', [
                'collection' => $collection,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 删除模型的所有媒体文件
     */
    public function deleteAllMedia(HasMedia $model): bool
    {
        try {
            $model->getMedia()->each->delete();

            return true;
        } catch (\Exception $e) {
            Log::error('删除所有媒体文件失败', [
                'model' => get_class($model),
                'model_id' => $model->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ==== 兼容旧方法 ====

    /**
     * 处理图片删除（兼容旧方法）
     */
    public function handleImagesDelete(HasMedia $model, array $ids, string $collection = 'images'): void
    {
        $this->deleteByIds($model, $ids, $collection);
    }

    /**
     * 处理图片排序（兼容旧方法）
     */
    public function handleImagesReorder(HasMedia $model, array $order, string $collection = 'images'): void
    {
        $this->updateOrder($model, $order, $collection);
    }

    // ==== 扩展功能 ====

    /**
     * 通过 URL 添加媒体文件
     *
     * @param HasMedia $model 模型实例
     * @param string $url 文件 URL
     * @param string $collection 集合名称
     * @param array $customProperties 自定义属性
     *
     * @throws UnreachableUrl
     */
    public function addMediaFromUrl(HasMedia $model, string $url, string $collection = 'default', array $customProperties = []): Media
    {
        /** @var InteractsWithMedia $model */
        return $model->addMediaFromUrl($url)
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection);
    }

    /**
     * 复制媒体文件到另一个模型
     *
     * @param HasMedia $sourceModel 源模型
     * @param HasMedia $targetModel 目标模型
     * @param string $sourceCollection 源集合
     * @param string $targetCollection 目标集合
     * @param array $mediaIds 要复制的媒体ID（为空则复制所有）
     * @return Collection<Media>
     */
    public function copyMediaBetweenModels(HasMedia $sourceModel, HasMedia $targetModel, string $sourceCollection = '', string $targetCollection = 'default', array $mediaIds = []): Collection
    {
        $sourceMedia = $sourceCollection ? $sourceModel->getMedia($sourceCollection) : $sourceModel->getMedia();

        if (! empty($mediaIds)) {
            $sourceMedia = $sourceMedia->whereIn('id', $mediaIds);
        }

        $copiedMedia = collect();

        foreach ($sourceMedia as $media) {
            try {
                $newMedia = $media->copy($targetModel, $targetCollection);
                $copiedMedia->push($newMedia);
            } catch (\Exception $e) {
                Log::error('复制媒体文件失败', [
                    'source_media_id' => $media->id,
                    'target_model' => get_class($targetModel),
                    'target_collection' => $targetCollection,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $copiedMedia;
    }

    /**
     * 获取媒体文件统计信息
     *
     * @param HasMedia $model 模型实例
     * @param string $collection 集合名称
     * @return array 统计信息
     */
    public function getMediaStats(HasMedia $model, string $collection = ''): array
    {
        $media = $collection ? $model->getMedia($collection) : $model->getMedia();

        $stats = [
            'total_files' => $media->count(),
            'total_size' => $media->sum('size'),
            'total_size_formatted' => AppHelper::formatFileSize($media->sum('size')),
            'file_types' => [],
            'collections' => [],
        ];

        // 按文件类型统计
        $typeStats = $media->groupBy('mime_type')->map(function ($files, $mimeType) {
            return [
                'count' => $files->count(),
                'total_size' => $files->sum('size'),
                'size_formatted' => AppHelper::formatFileSize($files->sum('size')),
            ];
        });

        $stats['file_types'] = $typeStats->all();

        // 按集合统计
        $collectionStats = $media->groupBy('collection_name')->map(function ($files, $collectionName) {
            return [
                'count' => $files->count(),
                'total_size' => $files->sum('size'),
                'size_formatted' => AppHelper::formatFileSize($files->sum('size')),
            ];
        });

        $stats['collections'] = $collectionStats->all();

        return $stats;
    }

    /**
     * 检查文件是否重复（基于文件内容哈希）
     *
     * @param HasMedia $model 模型实例
     * @param UploadedFile $file 上传文件
     * @param string $collection 集合名称
     * @return \Closure 如果存在重复文件则返回已存在的媒体实例
     */
    public function findDuplicateFile(HasMedia $model, UploadedFile $file, string $collection = ''): \Closure
    {
        //        $fileHash = md5_file($file->getRealPath());
        $fileName = $this->generateFileName($file);

        return ($collection ? $model->getMedia($collection) : $model->getMedia())->first(function (Media $item) use ($fileName) {
            return $item->file_name === $fileName;
        });
    }

    /**
     * 批量处理文件上传，支持去重
     *
     * @param HasMedia $model 模型实例
     * @param array<UploadedFile> $files 上传文件数组
     * @param string $collection 集合名称
     * @param bool $skipDuplicates 是否跳过重复文件
     * @param bool $preserveOrder 是否保持排序
     * @return array 处理结果
     */
    public function batchUpload(HasMedia $model, array $files, string $collection = 'images', bool $skipDuplicates = true, bool $preserveOrder = true): array
    {
        $results = [
            'uploaded' => collect(),
            'duplicates' => collect(),
            'errors' => collect(),
        ];

        $maxOrder = $preserveOrder ? ($model->getMedia($collection)->max('order_column') ?? 0) : 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                $results['errors']->push([
                    'file' => 'invalid',
                    'error' => '不是有效的上传文件对象',
                ]);

                continue;
            }

            try {
                // 检查重复
                if ($skipDuplicates) {
                    $duplicate = $this->findDuplicateFile($model, $file, $collection);
                    if ($duplicate) {
                        $results['duplicates']->push([
                            'original_file' => $file->getClientOriginalName(),
                            'existing_media' => $duplicate,
                        ]);

                        continue;
                    }
                }

                // 上传文件
                $media = $this->uploadSingle($model, $file, $collection);

                if ($preserveOrder) {
                    $maxOrder++;
                    $media->update(['order_column' => $maxOrder]);
                }

                $results['uploaded']->push($media);

            } catch (\Exception $e) {
                $results['errors']->push([
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'uploaded_count' => $results['uploaded']->count(),
            'duplicate_count' => $results['duplicates']->count(),
            'error_count' => $results['errors']->count(),
            'uploaded' => $results['uploaded']->all(),
            'duplicates' => $results['duplicates']->all(),
            'errors' => $results['errors']->all(),
        ];
    }

    /**
     * 清理无效的媒体文件（文件不存在但数据库记录存在）
     *
     * @param HasMedia $model 模型实例
     * @param string $collection 集合名称
     * @return int 清理的文件数量
     */
    public function cleanupInvalidMedia(HasMedia $model, string $collection = ''): int
    {
        $media = $collection ? $model->getMedia($collection) : $model->getMedia();
        $cleanedCount = 0;

        foreach ($media as $item) {
            try {
                // 尝试访问文件路径，如果文件不存在会抛出异常
                $item->getPath();
            } catch (\Exception $e) {
                // 文件不存在，删除数据库记录
                try {
                    $item->delete();
                    $cleanedCount++;
                    Log::info('清理无效媒体文件', ['media_id' => $item->id, 'file_name' => $item->file_name]);
                } catch (\Exception $deleteException) {
                    Log::error('删除无效媒体记录失败', [
                        'media_id' => $item->id,
                        'error' => $deleteException->getMessage(),
                    ]);
                }
            }
        }

        return $cleanedCount;
    }
}
