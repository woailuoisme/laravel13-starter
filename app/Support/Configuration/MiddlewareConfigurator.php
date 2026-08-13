<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Spatie\ResponseCache\Middlewares\CacheResponse;
use Spatie\ResponseCache\Middlewares\DoNotCacheResponse;

/**
 * 应用中间件配置类
 * 支持全局中间件、路由别名和分组中间件配置
 */
class MiddlewareConfigurator
{
    /**
     * 配置中间件
     * 支持全局中间件、路由别名和分组中间件配置
     */
    public static function configureMiddleware(Middleware $middleware): void
    {
        $middleware->redirectGuestsTo(static function (Request $request): ?string {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('filament.admin.auth.login');
            }

            return null;
        });

        // 注册全局中间件
        self::registerGlobalMiddleware($middleware);

        // 注册路由中间件别名
        self::registerMiddlewareAliases($middleware);

        // 为特定路由组添加中间件
        self::registerGroupMiddleware($middleware);

        $middleware->preventRequestForgery(except: [
            'wechat',
            'api/v1/stripe/webhook',
        ]);

        $middleware->preventRequestsDuringMaintenance(except: [
            'up',
            '/up',
            'ready',
            '/ready',
        ]);

        // 移除 API 组的频率限制（如果你在压测时不需要它）
        $middleware->api(remove: [
            ThrottleRequests::class,
        ]);

        // 或者替换某个中间件
        //        $middleware->web(replace: [
        //            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => \App\Http\Middleware\MyCustomCsrf::class,
        //        ]);
    }

    /**
     * 注册全局中间件
     */
    private static function registerGlobalMiddleware(Middleware $middleware): void
    {
        // 可根据需要启用
        // $middleware->append(\App\Http\Middleware\TrustProxies::class);
        // $middleware->append(\App\Http\Middleware\HandleCors::class);

        $middleware->web(append: [
            CacheResponse::class,
        ]);
    }

    /**
     * 注册中间件别名
     */
    private static function registerMiddlewareAliases(Middleware $middleware): void
    {
        $middleware->alias([
            //             'auth' => \App\Http\Middleware\Authenticate::class,
            //             'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            //             'admin' => \App\Http\Middleware\CheckAdminRole::class,
            'throttle' => ThrottleRequests::class,
            'doNotCacheResponse' => DoNotCacheResponse::class,
            'cacheResponse' => CacheResponse::class,
        ]);
    }

    /**
     * 注册分组中间件
     */
    private static function registerGroupMiddleware(Middleware $middleware): void
    {
        // 可根据需要启用
        // $middleware->group('api', [
        //     \App\Http\Middleware\LogApiRequests::class,
        //     \App\Http\Middleware\HandleApiResponse::class,
        // ]);
    }
}
