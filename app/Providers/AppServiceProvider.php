<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Providers;

use App\Models\System\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * 应用服务
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 注册系统设置服务
        $this->app->singleton(\App\Services\SettingManagerService::class, function () {
            return new \App\Services\SettingManagerService;
        });
        // 注册SMS
        $this->app->singleton(\Overtrue\EasySms\EasySms::class, function () {
            return new \Overtrue\EasySms\EasySms($this->app['config']['sms']);
        });
        // 注册微信公众号服务
        $this->app->singleton(\App\Services\WechatService::class, function () {
            return new \App\Services\WechatService;
        });
        // 注册微信小程序服务
        $this->app->singleton(\App\Services\WechatMiniService::class, function () {
            return new \App\Services\WechatMiniService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('zh');
        Model::shouldBeStrict(! $this->app->isProduction());
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        JsonResource::withoutWrapping();
        Relation::enforceMorphMap(config('morph_maps'));

        // 定义 API 速率限制器
        RateLimiter::for('api', function (object $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
