<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use Illuminate\Support\Facades\Route;

/**
 * 应用路由配置类
 * 提供版本化路由和默认路由配置
 */
class RouteConfigurator
{
    private const string API_PREFIX = 'api';

    private const string API_V1_PREFIX = 'api/v1';

    private const string API_V2_PREFIX = 'api/v2';

    /**
     * 配置API路由
     * 支持版本化路由和默认路由配置
     */
    public static function configureRoutes(): void
    {
        // 定义路由文件映射
        $routeFiles = self::getRouteFiles();

        // 配置v1版本路由
        self::configureV1Routes($routeFiles['v1']);

        // 配置v1版本管理员路由
        self::configureV1AdminRoutes($routeFiles['v1_admin']);

        // 配置v2版本管理员路由
        self::configureV2AdminRoutes($routeFiles['v2_admin']);

        // 配置默认API路由
        self::configureDefaultRoutes($routeFiles['default']);

        //        RateLimiter::for('api', function (Request $request) {
        //            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        //        });
    }

    /**
     * 获取路由文件配置
     *
     * @return array{
     *     v1: list<string>,
     *     v1_admin: list<string>,
     *     v1_customer: list<string>,
     *     default: list<string>,
     *     api: list<string>,
     *     v2_admin: list<string>
     * }
     */
    private static function getRouteFiles(): array
    {
        return [
            'v1' => [
                base_path('routes/api/v1/api.php'),
            ],
            'v1_admin' => [
                base_path('routes/api/v1/admin.php'),
            ],
            'v1_customer' => [
                base_path('routes/api/v1/customer.php'),
            ],
            'default' => [
                base_path('routes/api/default.php'),
            ],
            'api' => [
                base_path('routes/api.php'),
            ],
            'v2_admin' => [
                base_path('routes/api/v2/admin.php'),
            ],
        ];
    }

    /**
     * 配置v1版本路由
     *
     * @param  list<string>|string  $routes
     */
    private static function configureV1Routes(array|string $routes): void
    {
        Route::middleware('api')->prefix(self::API_V1_PREFIX)->name('v1.')->group($routes);
    }

    /**
     * 配置v1版本管理员路由
     *
     * @param  list<string>|string  $routes
     */
    private static function configureV1AdminRoutes(array|string $routes): void
    {
        Route::middleware(['api', 'auth:admin'])
            ->prefix(self::API_V1_PREFIX.'/admin')
            ->name('v1.admin.')
            ->group($routes);
    }

    /**
     * 配置v2版本管理员路由
     *
     * @param  list<string>|string  $routes
     */
    private static function configureV2AdminRoutes(array|string $routes): void
    {
        Route::middleware(['api', 'auth:admin'])
            ->prefix(self::API_V2_PREFIX.'/admin')
            ->name('v2.admin.')
            ->group($routes);
    }

    /**
     * 配置默认API路由
     *
     * @param  list<string>|string  $routes
     */
    private static function configureDefaultRoutes(array|string $routes): void
    {
        Route::middleware('api')->prefix(self::API_PREFIX)->group($routes);
    }
}
