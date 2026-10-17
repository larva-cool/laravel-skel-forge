<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Enums\SocialProvider;
use App\Models\Admin\Admin;
use App\Models\System\Social;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Overtrue\Socialite\User as SocialiteUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Social 模型单元测试
 */
#[CoversClass(Social::class)]
#[Group('models')]
#[Group('social')]
class SocialTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('provider 属性正确 cast 为 SocialProvider 枚举')]
    public function provider_is_cast_to_enum(): void
    {
        $user = User::factory()->create();
        $social = Social::create([
            'user_id' => $user->id,
            'user_type' => User::class,
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'wx_openid_123',
        ]);

        $this->assertInstanceOf(SocialProvider::class, $social->provider);
        $this->assertSame(SocialProvider::WECHAT_MP, $social->provider);
    }

    #[Test]
    #[TestDox('user 关系返回多态关联的用户')]
    public function user_returns_morph_to_relation(): void
    {
        $user = User::factory()->create();
        $social = Social::create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
            'provider' => SocialProvider::APPLE,
            'openid' => 'apple_id_456',
        ]);

        $this->assertInstanceOf(User::class, $social->user);
        $this->assertSame($user->id, $social->user->id);
    }

    #[Test]
    #[TestDox('user 关系也支持 Admin 模型')]
    public function user_supports_admin_model(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();
        $social = Social::create([
            'user_id' => $admin->id,
            'user_type' => $admin->getMorphClass(),
            'provider' => SocialProvider::DOUYIN,
            'openid' => 'douyin_789',
        ]);

        $this->assertInstanceOf(Admin::class, $social->user);
        $this->assertSame($admin->id, $social->user->id);
    }

    #[Test]
    #[TestDox('hidden 属性隐藏 user_id 和 user_type')]
    public function hidden_attributes(): void
    {
        $user = User::factory()->create();
        $social = Social::create([
            'user_id' => $user->id,
            'user_type' => User::class,
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'test',
        ]);

        $array = $social->toArray();

        $this->assertArrayNotHasKey('user_id', $array);
        $this->assertArrayNotHasKey('user_type', $array);
    }

    #[Test]
    #[TestDox('expiry_at 正确 cast 为 datetime')]
    public function expiry_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $social = Social::create([
            'user_id' => $user->id,
            'user_type' => User::class,
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'test',
            'expiry_at' => '2025-12-31 23:59:59',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $social->expiry_at);
    }

    #[Test]
    #[TestDox('ofUserType 作用域可匹配 morph map 别名存储的记录')]
    public function of_user_type_scope_matches_morph_alias(): void
    {
        $user = User::factory()->create();
        $user->socials()->create([
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'wx_openid_scope',
        ]);

        $this->assertSame('user', Social::query()->value('user_type'));
        $this->assertSame(1, Social::query()->ofUserType(User::class)->count());
        $this->assertSame(1, Social::query()->ofUserType($user)->count());
        $this->assertSame(0, Social::query()->ofUserType(Admin::class)->count());
    }

    #[Test]
    #[TestDox('ofOpenid 作用域按渠道与开放平台ID定位记录')]
    public function of_openid_scope_locates_record(): void
    {
        $user = User::factory()->create();
        $user->socials()->create([
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'wx_openid_target',
        ]);
        $user->socials()->create([
            'provider' => SocialProvider::APPLE,
            'openid' => 'wx_openid_target',
        ]);

        $social = Social::query()
            ->ofUserType(User::class)
            ->ofOpenid(SocialProvider::WECHAT_MP, 'wx_openid_target')
            ->first();

        $this->assertNotNull($social);
        $this->assertSame(SocialProvider::WECHAT_MP, $social->provider);
        $this->assertNull(
            Social::query()->ofOpenid(SocialProvider::DOUYIN, 'wx_openid_target')->first()
        );
    }

    #[Test]
    #[TestDox('updateCredentials 写入本次授权返回的凭证')]
    public function update_credentials_persists_tokens(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');

        $user = User::factory()->create();
        $social = $user->socials()->create([
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'wx_openid_update',
        ]);

        $social->updateCredentials(
            (new SocialiteUser(['id' => 'wx_openid_update']))
                ->setRaw(['openid' => 'wx_openid_update', 'unionid' => 'wx_unionid_update'])
                ->setAccessToken('new_access_token')
                ->setRefreshToken('new_refresh_token')
                ->setExpiresIn(7200)
        );

        $social->refresh();
        $this->assertSame('wx_unionid_update', $social->unionid);
        $this->assertSame('new_access_token', $social->access_token);
        $this->assertSame('new_refresh_token', $social->refresh_token);
        $this->assertSame('2026-08-21 12:00:00', $social->expiry_at->toDateTimeString());

        Carbon::setTestNow();
    }

    #[Test]
    #[TestDox('updateCredentials 不会用空值覆盖已有的 unionid')]
    public function update_credentials_keeps_existing_unionid(): void
    {
        $user = User::factory()->create();
        $social = $user->socials()->create([
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'wx_openid_keep',
            'unionid' => 'wx_unionid_keep',
        ]);

        $social->updateCredentials(
            (new SocialiteUser(['id' => 'wx_openid_keep']))
                ->setRaw(['openid' => 'wx_openid_keep'])
                ->setAccessToken('another_access_token')
                ->setExpiresIn(7200)
        );

        $social->refresh();
        $this->assertSame('wx_unionid_keep', $social->unionid);
        $this->assertSame('another_access_token', $social->access_token);
    }
}
