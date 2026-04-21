<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\multiselect;

#[Signature('test:disk {--disk=* : 指定要测试的存储磁盘（可多选）} {--all : 测试所有配置的存储磁盘}')]
#[Description('测试存储服务是否正常工作（支持 MinIO、Cloudflare R2、Garage、阿里云 OSS 等）')]
class TestStorageDisk extends Command
{
    /**
     * 执行带超时的操作
     */
    private function executeWithTimeout(callable $callback, int $timeout = 30): mixed
    {
        $startTime = time();
        $oldTimeLimit = (int) ini_get('max_execution_time');

        if ($oldTimeLimit > $timeout || $oldTimeLimit === 0) {
            set_time_limit($timeout + 5);
        }

        try {
            $result = $callback();

            if (time() - $startTime >= $timeout) {
                throw new RuntimeException('操作超时');
            }

            set_time_limit($oldTimeLimit);

            return $result;
        } catch (Throwable $e) {
            set_time_limit($oldTimeLimit);

            if (time() - $startTime >= $timeout) {
                throw new RuntimeException('操作超时: ' . $e->getMessage());
            }

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * 执行测试命令
     */
    public function handle(): int
    {
        set_time_limit(600);
        $this->info('设置执行时间限制为 10 分钟...');

        if ($this->option('all')) {
            return $this->testAllDisks();
        }

        $disks = (array) $this->option('disk');

        if (empty($disks)) {
            $disks = $this->selectDisksInteractively();
            if (empty($disks)) {
                return self::FAILURE;
            }
        }

        return $this->testSpecifiedDisks($disks);
    }

    /**
     * 测试所有配置的存储磁盘
     */
    private function testAllDisks(): int
    {
        $disks = $this->getAvailableDisks();
        $results = [];

        foreach ($disks as $disk) {
            $this->newLine();
            $this->info("=== 测试 {$disk} 存储服务 ===");

            try {
                $this->testSingleDiskLogic($disk);
                $results[$disk] = true;
                $this->info("{$disk} 存储服务测试通过");
            } catch (Throwable $e) {
                $results[$disk] = false;
                $this->error("{$disk} 存储服务测试失败: " . $e->getMessage());
            }
        }

        $this->showSummary($results);

        return ! in_array(false, $results, true) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 测试指定的存储磁盘
     */
    private function testSpecifiedDisks(array $disks): int
    {
        $availableDisks = $this->getAvailableDisks();
        $results = [];

        foreach ($disks as $disk) {
            if (! in_array($disk, $availableDisks, true)) {
                $this->error("磁盘 '{$disk}' 不存在或配置不完整");
                continue;
            }

            $this->newLine();
            $this->info("开始测试 {$disk} 存储服务...");

            try {
                $this->testSingleDiskLogic($disk);
                $results[$disk] = true;
                $this->info("{$disk} 存储服务测试通过");
            } catch (Throwable $e) {
                $results[$disk] = false;
                $this->error("{$disk} 存储服务测试失败: " . $e->getMessage());
            }
        }

        if (count($disks) > 1) {
            $this->showSummary($results);
        }

        return ! in_array(false, $results, true) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 执行单个磁盘的测试逻辑
     */
    private function testSingleDiskLogic(string $disk): void
    {
        $storage = Storage::disk($disk);

        $steps = [
            '连接' => fn () => $this->testConnection($storage),
            '文件上传' => fn () => $this->testFileUpload($storage, $disk),
            '文件读取' => fn ($file) => $this->testFileRead($storage, $file),
            '文件列表' => fn () => $this->testFileList($storage),
            '文件删除' => fn ($file) => $this->testFileDelete($storage, $file),
        ];

        $testFile = null;
        foreach ($steps as $name => $logic) {
            $this->info("- 测试{$name}...");
            if ($name === '文件读取' || $name === '文件删除') {
                $logic($testFile);
            } else {
                $result = $logic();
                if ($name === '文件上传') {
                    $testFile = $result;
                }
            }
            $this->line("  [OK] {$name}测试通过");
        }
    }

    private function testConnection(Filesystem $storage): void
    {
        $this->executeWithTimeout(fn () => $storage->directories(), 10);
    }

    private function testFileUpload(Filesystem $storage, string $disk): string
    {
        $testContent = "{$disk} 存储测试文件内容 - " . now()->toDateTimeString();
        $testFileName = "test/{$disk}-test-" . time() . '.txt';

        $this->executeWithTimeout(fn () => $storage->put($testFileName, $testContent), 30);

        if (! $storage->exists($testFileName)) {
            throw new RuntimeException('文件上传后未检测到存在');
        }

        return $testFileName;
    }

    private function testFileRead(Filesystem $storage, string $fileName): void
    {
        $content = $this->executeWithTimeout(fn () => $storage->get($fileName), 30);
        if (empty($content)) {
            throw new RuntimeException('文件读取内容为空');
        }
    }

    private function testFileList(Filesystem $storage): void
    {
        $this->executeWithTimeout(fn () => $storage->files('test'), 30);
    }

    private function testFileDelete(Filesystem $storage, string $fileName): void
    {
        $this->executeWithTimeout(fn () => $storage->delete($fileName), 30);
        if ($storage->exists($fileName)) {
            throw new RuntimeException('文件删除后仍然存在');
        }
    }

    private function getAvailableDisks(): array
    {
        $disks = config('filesystems.disks', []);
        $availableDisks = [];

        foreach ($disks as $name => $config) {
            if (in_array($name, ['local', 'public', 's3'], true)) {
                continue;
            }

            if (empty($config['driver'])) {
                continue;
            }

            // 基本验证配置是否完整
            if ($config['driver'] === 's3' && (empty($config['key']) || empty($config['bucket']))) {
                continue;
            }

            $availableDisks[] = (string) $name;
        }

        return $availableDisks;
    }

    private function selectDisksInteractively(): array
    {
        $availableDisks = $this->getAvailableDisks();

        if (empty($availableDisks)) {
            $this->error('没有找到可用的存储磁盘配置');
            return [];
        }

        return multiselect(
            label: '请选择要测试的存储磁盘',
            options: $availableDisks,
            required: true,
        );
    }

    private function showSummary(array $results): void
    {
        $this->newLine();
        $this->info('=== 测试结果汇总 ===');
        foreach ($results as $disk => $success) {
            $status = $success ? '<info>通过</info>' : '<error>失败</error>';
            $this->line("{$disk}: {$status}");
        }
    }
}
