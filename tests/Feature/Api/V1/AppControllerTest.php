<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\AppPlatform;
use App\Http\Controllers\Api\V1\AppController;
use App\Models\App\AppRelease;
use App\Models\App\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 客户端 App 版本检查更新功能测试
 */
#[CoversClass(AppController::class)]
#[Group('api')]
#[Group('app')]
class AppControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('无更新时返回 has_update=false')]
    public function check_update_returns_no_update_when_latest(): void
    {
        $app = AppRelease::factory()->create(['slug' => 'test-app', 'status' => true]);
        AppVersion::factory()->published()->create([
            'app_release_id' => $app->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10000,
        ]);

        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'test-app',
            'platform' => 'android',
            'version' => '1.0.0',
            'build' => 10000,
        ])->assertOk()
            ->assertJsonPath('has_update', false)
            ->assertJsonPath('force_update', false)
            ->assertJsonPath('latest_version', null);
    }

    #[Test]
    #[TestDox('有更新时返回 has_update=true 及版本详情')]
    public function check_update_returns_update_available(): void
    {
        $app = AppRelease::factory()->create(['slug' => 'test-app', 'status' => true]);
        AppVersion::factory()->published()->create([
            'app_release_id' => $app->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10001,
            'version' => '1.0.1',
            'download_url' => 'https://example.com/v1.0.1.apk',
        ]);

        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'test-app',
            'platform' => 'android',
            'version' => '1.0.0',
            'build' => 10000,
        ])->assertOk()
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('force_update', false)
            ->assertJsonPath('latest_version.version', '1.0.1')
            ->assertJsonPath('latest_version.download_url', 'https://example.com/v1.0.1.apk');
    }

    #[Test]
    #[TestDox('低于最低支持构建号时返回 force_update=true')]
    public function check_update_returns_force_update_when_below_min(): void
    {
        $app = AppRelease::factory()->create(['slug' => 'test-app', 'status' => true]);
        AppVersion::factory()->published()->create([
            'app_release_id' => $app->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10001,
            'min_supported_build' => 10000,
        ]);

        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'test-app',
            'platform' => 'android',
            'version' => '0.9.0',
            'build' => 9000,
        ])->assertOk()
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('force_update', true);
    }

    #[Test]
    #[TestDox('force_update 标记为 true 时返回 force_update=true')]
    public function check_update_returns_force_update_when_flag_is_true(): void
    {
        $app = AppRelease::factory()->create(['slug' => 'test-app', 'status' => true]);
        AppVersion::factory()->published()->forceUpdate()->create([
            'app_release_id' => $app->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10001,
            'min_supported_build' => 0,
        ]);

        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'test-app',
            'platform' => 'android',
            'version' => '1.0.0',
            'build' => 10000,
        ])->assertOk()
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('force_update', true);
    }

    #[Test]
    #[TestDox('应用不存在时返回 has_update=false')]
    public function check_update_returns_no_update_when_app_not_found(): void
    {
        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'nonexistent-app',
            'platform' => 'android',
            'version' => '1.0.0',
            'build' => 10000,
        ])->assertOk()
            ->assertJsonPath('has_update', false)
            ->assertJsonPath('force_update', false)
            ->assertJsonPath('latest_version', null);
    }

    #[Test]
    #[TestDox('应用被禁用时返回 has_update=false')]
    public function check_update_returns_no_update_when_app_disabled(): void
    {
        $app = AppRelease::factory()->create(['slug' => 'disabled-app', 'status' => false]);
        AppVersion::factory()->published()->create([
            'app_release_id' => $app->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10001,
        ]);

        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'disabled-app',
            'platform' => 'android',
            'version' => '1.0.0',
            'build' => 10000,
        ])->assertOk()
            ->assertJsonPath('has_update', false);
    }

    #[Test]
    #[TestDox('平台无已发布版本时返回 has_update=false')]
    public function check_update_returns_no_update_when_no_published_version(): void
    {
        $app = AppRelease::factory()->create(['slug' => 'test-app', 'status' => true]);
        AppVersion::factory()->create([ // draft
            'app_release_id' => $app->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10001,
        ]);

        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'test-app',
            'platform' => 'android',
            'version' => '1.0.0',
            'build' => 10000,
        ])->assertOk()
            ->assertJsonPath('has_update', false);
    }

    #[Test]
    #[TestDox('不同平台互不影响')]
    public function check_update_isolated_by_platform(): void
    {
        $app = AppRelease::factory()->create(['slug' => 'test-app', 'status' => true]);
        // Android 有新版本
        AppVersion::factory()->published()->create([
            'app_release_id' => $app->id,
            'platform' => AppPlatform::ANDROID,
            'build' => 10001,
        ]);
        // iOS 无已发布版本
        AppVersion::factory()->create([
            'app_release_id' => $app->id,
            'platform' => AppPlatform::IOS,
            'build' => 10001,
            'status' => \App\Enums\ReleaseStatus::DRAFT,
        ]);

        // Android 有更新
        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'test-app',
            'platform' => 'android',
            'version' => '1.0.0',
            'build' => 10000,
        ])->assertOk()
            ->assertJsonPath('has_update', true);

        // iOS 无更新
        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'test-app',
            'platform' => 'ios',
            'version' => '1.0.0',
            'build' => 10000,
        ])->assertOk()
            ->assertJsonPath('has_update', false);
    }

    #[Test]
    #[TestDox('缺少必填参数返回 422')]
    public function check_update_requires_all_fields(): void
    {
        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'test-app',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['platform', 'version', 'build']);
    }

    #[Test]
    #[TestDox('不支持的平台返回 422')]
    public function check_update_invalid_platform(): void
    {
        $this->postJson(route('api.v1.apps.check_update'), [
            'slug' => 'test-app',
            'platform' => 'windows',
            'version' => '1.0.0',
            'build' => 10000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }
}
