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
use Illuminate\Validation\Rules\Password as PasswordRule;

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

            // 密码强度配置（驱动 Illuminate\Validation\Rules\Password::defaults()）
            $this->applyPasswordDefaults($instance);
        } catch (\Exception $e) {
            Log::warning($e->getMessage());
        }
    }

    /**
     * 将数据库中的 password.strength.* 配置转换为 Laravel Password 验证规则。
     *
     * 业务侧通过 Password::defaults() 或 Password::default() 获取默认规则。
     */
    protected function applyPasswordDefaults(SettingManagerService $settings): void
    {
        $enabled = (bool) $settings->get('password.strength.enabled', true);
        $forceInProduction = (bool) $settings->get('password.strength.force_in_production', false);
        $enabled = $enabled || ($forceInProduction && $this->app->isProduction());

        $min = max((int) $settings->get('password.strength.min', 8), 1);
        $max = $settings->get('password.strength.max');

        PasswordRule::defaults(static function () use ($enabled, $min, $max): PasswordRule {
            $rule = PasswordRule::min($min);

            if ($max !== null && (int) $max > 0) {
                $rule = $rule->max((int) $max);
            }

            if (! $enabled) {
                return $rule;
            }

            /** @var SettingManagerService $settings */
            $settings = app(SettingManagerService::class);
            if ((bool) $settings->get('password.strength.mixed_case')) {
                $rule = $rule->mixedCase();
            }
            if ((bool) $settings->get('password.strength.letters')) {
                $rule = $rule->letters();
            }
            if ((bool) $settings->get('password.strength.numbers')) {
                $rule = $rule->numbers();
            }
            if ((bool) $settings->get('password.strength.symbols')) {
                $rule = $rule->symbols();
            }
            if ((bool) $settings->get('password.strength.uncompromised')) {
                $threshold = (int) $settings->get('password.strength.uncompromised_threshold', 0);
                $rule = $rule->uncompromised($threshold);
            }

            return $rule;
        });
    }
}
