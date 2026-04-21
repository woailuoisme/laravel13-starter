<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

#[Signature('bucket:clear 
    {--path= : 指定要删除的路径前缀} 
    {--disk=garage : 指定存储磁盘} 
    {--force : 强制删除} 
    {--media-only : 仅删除媒体库记录} 
    {--files-only : 仅删除文件} 
    {--dry-run : 模拟执行}')]
#[Description('清空存储桶数据，支持同步清理 Spatie MediaLibrary 媒体库记录')]
class ClearBucketCommand extends Command
{
    /**
     * 执行命令主逻辑
     */
    public function handle(): int
    {
        $disk = (string) $this->option('disk');
        $path = $this->option('path') ? (string) $this->option('path') : '';

        if (empty($disk) || ! config("filesystems.disks.$disk")) {
            $this->error("磁盘 [" . ($disk ?: 'NULL') . "] 未在 config/filesystems.php 中配置！");

            return self::FAILURE;
        }

        $storage = Storage::disk($disk);

        $this->info("正在扫描磁盘 [" . $disk . "] 路径 [/" . $path . "] 下的文件...");
        $files = $storage->allFiles($path);

        if (empty($files)) {
            $this->info('没有找到需要处理的文件。');

            return self::SUCCESS;
        }

        $this->info(sprintf('找到 %d 个候选文件。', count($files)));

        if ($this->option('dry-run')) {
            $this->comment('!!! 当前为模拟模式 (Dry Run)，不执行实际删除 !!!');
            foreach (array_slice($files, 0, 10) as $f) {
                $this->line("  - [模拟删除] {$f}");
            }

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("确定要彻底删除磁盘 [" . $disk . "] 下的这 " . count($files) . " 个文件吗？", false)) {
            $this->warn('操作已中止。');

            return self::SUCCESS;
        }

        return $this->processDeletion($files, $disk, $storage);
    }

    /**
     * 执行删除逻辑
     */
    private function processDeletion(array $files, string $disk, $storage): int
    {
        $metrics = ['files' => 0, 'media' => 0];

        // 显式创建并手动推进进度条，避免闭包参数混淆
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $file) {
            try {
                // 1. 同步清理 Spatie MediaLibrary 记录
                if (! $this->option('files-only') && class_exists(Media::class)) {
                    $mediaQuery = Media::where('disk', $disk)
                        ->where('file_name', basename($file));

                    // 如果路径包含 ID，尝试更精准匹配
                    $pathParts = explode('/', $file);
                    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
                        $mediaQuery->where('model_id', $pathParts[1]);
                    }

                    $metrics['media'] += (int) $mediaQuery->delete();
                }

                // 2. 删除物理文件
                if (! $this->option('media-only')) {
                    if ($storage->exists($file) && $storage->delete($file)) {
                        $metrics['files']++;
                    }
                }
            } catch (Throwable $e) {
                // 遇到错误时建议先暂停进度条，打印后继续
                $this->newLine();
                $this->error("处理文件 [" . $file . "] 时遇到异常: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("清理完成！删除了 {$metrics['files']} 个物理文件，清理了 {$metrics['media']} 条媒体记录。");

        return self::SUCCESS;
    }
}
