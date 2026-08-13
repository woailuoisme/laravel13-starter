<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Jobs\TestHorizonJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/**
 * 应用任务调度配置类
 * 提供常用的定时任务配置模板
 */
class ScheduleConfigurator
{
    /**
     * 配置任务调度
     * 提供常用的定时任务配置模板
     */
    public static function configureSchedule(): void
    {
        // 系统维护任务
        self::configureMaintenanceTasks();

        // 数据处理任务
        self::configureDataTasks();

        // 监控和日志任务
        self::configureMonitoringTasks();
    }

    /**
     * 配置系统维护任务
     */
    private static function configureMaintenanceTasks(): void
    {
        // 数据库备份任务
        Schedule::command('backup:run --only-db')
            ->daily()
            ->at('02:00')
            ->sendOutputTo(storage_path('logs/backup.log'))
            ->timezone('Asia/Shanghai')
            ->onFailure(static function (): void {
                // 备份失败通知逻辑
            });
        //        Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyFiveMinutes();
        Schedule::command('backup:clean')->daily()->at('01:00');
    }

    /**
     * 配置数据处理任务
     */
    private static function configureDataTasks(): void
    {
        // 数据同步任务
        // Schedule::command('data:sync')
        //     ->hourly()
        //     ->withoutOverlapping();

        // 统计数据生成
        // Schedule::command('statistics:generate')
        //     ->daily()
        //     ->at('05:00');

        //        // 每分钟检查设备状态并发送告警
        //        Schedule::call(static function () {
        //            $monitorService = new DeviceMonitorService();
        //            $monitorService->sendOfflineAlerts();
        //        })->everyMinute();
        //
        //        // 每5分钟记录设备状态统计
        //        Schedule::call(static function () {
        //            $monitorService = new DeviceMonitorService();
        //            $stats = $monitorService->getDeviceStats();
        //            Log::info('设备状态统计', $stats);
        //        })->everyFiveMinutes();
        Schedule::command('orders:cleanup-expired')->daily()->at('22:30');
        Schedule::command('device-failure-logs:cleanup --days=1 -f')->at('01:00');
        Schedule::command('scribe:generate')->daily()->at('04:00');

        // 每年6月30日凌晨2点清空所有用户积分
        //        Schedule::command('integral:clear --force')
        //            ->yearlyOn(6, 30, '11:59')
        //            ->timezone('Asia/Shanghai')
        //            ->sendOutputTo(storage_path('logs/integral-clear.log'))
        //            ->onSuccess(function () {
        //                Log::info('积分清空任务执行成功 - 6月30日');
        //            })
        //            ->onFailure(function () {
        //                Log::error('积分清空任务执行失败 - 6月30日');
        //            });
        //
        //        // 每年12月31日凌晨2点清空所有用户积分
        //        Schedule::command('integral:clear --force')
        //            ->yearlyOn(12, 31, '11:59')
        //            ->timezone('Asia/Shanghai')
        //            ->sendOutputTo(storage_path('logs/integral-clear.log'))
        //            ->onSuccess(function () {
        //                Log::info('积分清空任务执行成功 - 12月31日');
        //            })
        //            ->onFailure(function () {
        //                Log::error('积分清空任务执行失败 - 12月31日');
        //            });
    }

    /**
     * 配置监控和日志任务
     */
    private static function configureMonitoringTasks(): void
    {
        // 清理Telescope日志
        // Schedule::command('telescope:prune --hours=48')->daily()->at('03:00');
        // 每小时清理一次临时缓存数据
        //        Schedule::command('pulse:clear')->hourly();
        // 自定义命令：删除90天前的Pulse历史数据
        //        Schedule::command('pulse:purge')->dailyAt('01:00');

        // Horizon 快照（本地开发无需指标图表，仅在非本地环境收集）
        if (! app()->isLocal()) {
            Schedule::command('horizon:snapshot')->everyFiveMinutes();
        }

        // Horizon 和 Scheduler 联调健康度测试任务
        Schedule::job(new TestHorizonJob)->everyMinute()->before(static function (): void {
            Log::info('Scheduler dispatched TestHorizonJob.');
        });
    }
}
