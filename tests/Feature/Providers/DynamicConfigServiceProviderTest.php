<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Models\System\Setting;
use App\Providers\DynamicConfigServiceProvider;
use App\Services\SettingManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rules\Password as PasswordRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * DynamicConfigServiceProvider 测试
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[CoversClass(DynamicConfigServiceProvider::class)]
#[Group('providers')]
class DynamicConfigServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->delete();
        app(SettingManagerService::class)->clearCache();
        Config::set('filesystems.default', 'local');
    }

    /**
     * 测试 upload.storage 写入到 filesystems.default。
     */
    #[Test]
    #[TestDox('upload.storage 会写入 filesystems.default')]
    public function boot_applies_upload_storage_setting(): void
    {
        Setting::create(['key' => 'upload.storage', 'value' => 'public', 'cast_type' => 'string']);

        $this->bootProvider();

        $this->assertSame('public', Config::get('filesystems.default'));
    }

    /**
     * 测试 Password::defaults() 至少设置 min。
     */
    #[Test]
    #[TestDox('Password::defaults() 至少使用 password.strength.min 作为最小长度')]
    public function password_defaults_uses_min_length_from_settings(): void
    {
        Setting::create(['key' => 'password.strength.min', 'value' => '12', 'cast_type' => 'int']);

        $this->bootProvider();

        $applied = PasswordRule::default()->appliedRules();
        $this->assertSame(12, $applied['min']);
        $this->assertNull($applied['max']);
        $this->assertFalse($applied['mixedCase']);
        $this->assertFalse($applied['uncompromised']);
    }

    /**
     * 测试 Password::defaults() 应用 max。
     */
    #[Test]
    #[TestDox('Password::defaults() 启用 max(正整数) 时会写入 max')]
    public function password_defaults_applies_max_when_configured(): void
    {
        Setting::create(['key' => 'password.strength.max', 'value' => '64', 'cast_type' => 'int']);

        $this->bootProvider();

        $applied = PasswordRule::default()->appliedRules();
        $this->assertSame(64, $applied['max']);
    }

    /**
     * 测试 Password::defaults() 应用 mixedCase。
     */
    #[Test]
    #[TestDox('Password::defaults() 会根据 mixed_case 设置决定是否 mixedCase()')]
    public function password_defaults_applies_mixed_case_when_enabled(): void
    {
        Setting::create(['key' => 'password.strength.mixed_case', 'value' => '1', 'cast_type' => 'bool']);

        $this->bootProvider();

        $this->assertTrue(PasswordRule::default()->appliedRules()['mixedCase']);
    }

    /**
     * 测试 Password::defaults() 应用 numbers / symbols / letters。
     */
    #[Test]
    #[TestDox('Password::defaults() 会根据 numbers/symbols/letters 决定附加规则')]
    public function password_defaults_applies_optional_rules(): void
    {
        Setting::create(['key' => 'password.strength.numbers', 'value' => '1', 'cast_type' => 'bool']);
        Setting::create(['key' => 'password.strength.symbols', 'value' => '1', 'cast_type' => 'bool']);
        Setting::create(['key' => 'password.strength.letters', 'value' => '1', 'cast_type' => 'bool']);

        $this->bootProvider();

        $applied = PasswordRule::default()->appliedRules();
        $this->assertTrue($applied['numbers']);
        $this->assertTrue($applied['symbols']);
        $this->assertTrue($applied['letters']);
    }

    /**
     * 测试 Password::defaults() 应用 uncompromised 与阈值。
     */
    #[Test]
    #[TestDox('Password::defaults() 会根据 uncompromised/uncompromised_threshold 启用 uncompromised')]
    public function password_defaults_applies_uncompromised_with_threshold(): void
    {
        Setting::create(['key' => 'password.strength.uncompromised', 'value' => '1', 'cast_type' => 'bool']);
        Setting::create(['key' => 'password.strength.uncompromised_threshold', 'value' => '3', 'cast_type' => 'int']);

        $this->bootProvider();

        $applied = PasswordRule::default()->appliedRules();
        $this->assertTrue($applied['uncompromised']);
        $this->assertSame(3, $applied['compromisedThreshold']);
    }

    /**
     * 测试关闭 enabled 时不附加任何强度规则。
     */
    #[Test]
    #[TestDox('password.strength.enabled=0 时 Password::defaults() 仅保留 min/max')]
    public function password_defaults_only_min_when_disabled(): void
    {
        Setting::create(['key' => 'password.strength.enabled', 'value' => '0', 'cast_type' => 'bool']);
        Setting::create(['key' => 'password.strength.mixed_case', 'value' => '1', 'cast_type' => 'bool']);
        Setting::create(['key' => 'password.strength.numbers', 'value' => '1', 'cast_type' => 'bool']);
        Setting::create(['key' => 'password.strength.symbols', 'value' => '1', 'cast_type' => 'bool']);
        Setting::create(['key' => 'password.strength.uncompromised', 'value' => '1', 'cast_type' => 'bool']);

        $this->bootProvider();

        $applied = PasswordRule::default()->appliedRules();
        $this->assertSame(8, $applied['min']);
        $this->assertFalse($applied['mixedCase']);
        $this->assertFalse($applied['numbers']);
        $this->assertFalse($applied['symbols']);
        $this->assertFalse($applied['uncompromised']);
    }

    /**
     * 测试 max <= 0 时不应用 max。
     */
    #[Test]
    #[TestDox('password.strength.max <= 0 时 Password::defaults() 不附加 max 约束')]
    public function password_defaults_ignores_non_positive_max(): void
    {
        Setting::create(['key' => 'password.strength.max', 'value' => '0', 'cast_type' => 'int']);

        $this->bootProvider();

        $this->assertNull(PasswordRule::default()->appliedRules()['max']);
    }

    /**
     * 调用 ServiceProvider 的 boot 方法。
     */
    protected function bootProvider(): void
    {
        $provider = new DynamicConfigServiceProvider($this->app);
        $provider->boot();
    }
}
