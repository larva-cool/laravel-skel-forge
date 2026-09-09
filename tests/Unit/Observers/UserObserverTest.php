<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Enums\SocialProvider;
use App\Models\Invite\InviteCode;
use App\Models\System\LoginHistory;
use App\Models\System\Social;
use App\Models\User;
use App\Models\User\UserExtra;
use App\Models\User\UserProfile;
use App\Observers\UserObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * UserObserver 单元测试
 */
#[CoversClass(UserObserver::class)]
#[Group('observers')]
class UserObserverTest extends TestCase
{
    use RefreshDatabase;

    private UserObserver $observer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->observer = new UserObserver;
    }

    #[Test]
    #[TestDox('created 事件创建 UserExtra、UserProfile 和邀请码记录')]
    public function created_creates_user_extra_profile_and_invite_code(): void
    {
        $user = User::factory()->create();

        // UserObserver 已通过 Laravel 自动注册，验证 extra、profile 和邀请码已创建
        $this->assertDatabaseHas('user_extras', ['user_id' => $user->id]);
        $this->assertInstanceOf(UserExtra::class, $user->extra);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id]);
        $this->assertInstanceOf(UserProfile::class, $user->profile);
        $this->assertDatabaseHas('invite_codes', [
            'owner_id' => $user->id,
            'owner_type' => $user->getMorphClass(),
        ]);
        $this->assertInstanceOf(InviteCode::class, $user->inviteCode);
    }

    #[Test]
    #[TestDox('created 手动调用也创建 UserExtra、UserProfile 和邀请码')]
    public function created_manual_call_creates_extra_profile_and_invite_code(): void
    {
        $user = User::factory()->create();
        // 删除 extra、profile 和邀请码后手动调用
        $user->extra?->delete();
        $user->profile?->delete();
        $user->inviteCode?->delete();

        $this->observer->created($user);

        $this->assertDatabaseHas('user_extras', ['user_id' => $user->id]);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseHas('invite_codes', [
            'owner_id' => $user->id,
            'owner_type' => $user->getMorphClass(),
        ]);
    }

    #[Test]
    #[TestDox('forceDeleted 删除 UserExtra、UserProfile、邀请码、登录历史和社交账号记录')]
    public function force_deleted_deletes_user_extra_profile_invite_code_login_histories_and_socials(): void
    {
        $user = User::factory()->create();
        $extra = $user->extra;
        $profile = $user->profile;

        // 创建一些登录历史记录
        LoginHistory::factory()->count(3)->create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
        ]);

        // 创建一些社交账号记录
        Social::create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'openid123',
            'unionid' => 'unionid123',
            'access_token' => 'token123',
            'refresh_token' => 'refresh123',
            'expiry_at' => now()->addDays(30),
        ]);

        $this->assertNotNull($extra);
        $this->assertNotNull($profile);
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id]);
        $this->assertDatabaseHas('socials', ['user_id' => $user->id]);

        $this->observer->forceDeleted($user);

        $this->assertDatabaseMissing('user_extras', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('invite_codes', [
            'owner_id' => $user->id,
            'owner_type' => $user->getMorphClass(),
        ]);
        $this->assertDatabaseMissing('login_histories', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('socials', ['user_id' => $user->id]);
    }
}
