<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:init-db {--force : 强制执行，不进行确认}')]
#[Description('系统数据库初始化命令，重置迁移、清理存储桶并运行种子填充')]
class InitDb extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('是否要重置数据库并清除所有数据（migrate:fresh）?', true)) {
            $this->warn('数据库初始化已取消。');

            return self::SUCCESS;
        }

        $this->info('正在重置数据库...');
        $this->call('migrate:fresh');
        $this->info('数据库迁移已重置完成。');

        // 调用刚才优化的 bucket:clear 命令
        $this->info('正在清理存储桶数据...');
        $this->call('bucket:clear', [
            '--disk' => 'minio',
            '--force' => true,
        ]);

        $this->info('正在注入种子数据...');
        $this->call('db:seed');

        $this->info('系统数据库初始化成功！');

        return self::SUCCESS;
    }
}
