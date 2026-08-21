<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon 队列监控服务提供者
 *
 * Horizon 自带 Dashboard（/horizon）使用 web guard（Session 认证），
 * 通过 viewHorizon Gate 控制访问权限。
 *
 * 管理后台（前端 SPA）通过 /admin/queue/* 代理路由访问 Horizon 数据，
 * 由 Admin\QueueController 直接调用 Horizon Contracts，受 auth:admin
 * 中间件保护，与 Horizon 自带路由完全独立。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * 此 Gate 限定谁能在非本地环境访问 Horizon Dashboard（/horizon）。
     * Admin API 代理路由不经过此 Gate，走独立的 auth:admin + permission 中间件。
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if ($user === null) {
                return false;
            }

            // 邮箱白名单
            $allowedEmails = [
                // 'admin@example.com',
            ];
            if (in_array($user->email ?? '', $allowedEmails, true)) {
                return true;
            }

            // Admin 模型通过 Spatie Permission 判断角色/权限
            if (method_exists($user, 'hasRole')) {
                return $user->hasRole('super-admin') || $user->can('horizon:view');
            }

            return false;
        });
    }
}
