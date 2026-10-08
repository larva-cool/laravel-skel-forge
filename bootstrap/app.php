<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Psr\Log\LogLevel;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/open.php'
        ],
        api: [
            __DIR__.'/../routes/api_v1.php',
            // __DIR__.'/../routes/api_v2.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', \App\Http\Middleware\RecordAuditLog::class])
                ->prefix('admin')
                ->as('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        // $middleware->authenticateSessions();
        $middleware->throttleApi(redis: false);
        // 允许同站请求
        $middleware->preventRequestForgery(allowSameSite: true);
        $middleware->alias([
            'abilities' => Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class, // 角色
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class, // 权限
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class, // 角色权限
        ]);
        // Configure the CSRF token validation middleware.
        $middleware->preventRequestForgery([
            '/admin/*',
            '/api/*',
            '/open/*',
        ]);
        // Configure the cookie encryption middleware.
        // $middleware->encryptCookies([
        //     //
        // ]);
        // 配置信任的代理 IP
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '10.0.0.0/8',
            '100.64.0.0/10',
            '172.16.0.0/16',
            '192.168.0.0/16',
        ]);
        // 配置信任的代理头
        $middleware->trustProxies(headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PREFIX |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );
        // Configure the URL signature validation middleware.
        // $middleware->validateSignatures([
        //     'fbclid',
        //     'utm_campaign',
        //     'utm_content',
        //     'utm_medium',
        //     'utm_source',
        //     'utm_term',
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->level(PDOException::class, LogLevel::CRITICAL);
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->is('admin/*') || $request->is('open/*'),
        );
    })->create();
