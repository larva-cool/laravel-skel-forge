<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Providers;

use App\Services\SettingManagerService;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * 动态配置服务提供器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class DynamicConfigServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            $instance = Container::getInstance()->make(SettingManagerService::class);
            // 上传配置
            if ($instance->has('upload.storage')) {
                Config::set('filesystems.default', $instance->get('upload.storage'));
            }
        } catch (\Exception $e) {
            Log::warning($e->getMessage());
        }
    }
}
