<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\User;

use App\Models\User;
use App\Models\User\UserExtra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * UserExtra 模型单元测试
 */
#[CoversClass(UserExtra::class)]
#[Group('models')]
#[Group('user-extra')]
class UserExtraTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('username_change_count 默认为 0')]
    public function username_change_count_defaults_to_zero(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, $user->extra->username_change_count);
    }

    #[Test]
    #[TestDox('user 关系返回关联的用户')]
    public function user_returns_related_user(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user->extra->user);
        $this->assertSame($user->id, $user->extra->user->id);
    }

    #[Test]
    #[TestDox('user_id 正确 cast 为 integer')]
    public function user_id_is_cast_to_integer(): void
    {
        $user = User::factory()->create();

        $this->assertIsInt($user->extra->user_id);
    }

    #[Test]
    #[TestDox('username_change_count 正确 cast 为 integer')]
    public function username_change_count_is_cast_to_integer(): void
    {
        $user = User::factory()->create();
        $user->extra->forceFill(['username_change_count' => 3])->save();
        $user->extra->refresh();

        $this->assertIsInt($user->extra->username_change_count);
        $this->assertSame(3, $user->extra->username_change_count);
    }

    #[Test]
    #[TestDox('hidden 属性隐藏 user_id')]
    public function hidden_attributes(): void
    {
        $user = User::factory()->create();
        $array = $user->extra->toArray();

        $this->assertArrayNotHasKey('user_id', $array);
    }

    #[Test]
    #[TestDox('UserObserver 创建用户时自动创建 UserExtra')]
    public function user_observer_creates_extra_on_user_created(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('user_extras', ['user_id' => $user->id]);
        $this->assertInstanceOf(UserExtra::class, $user->extra);
        $this->assertSame(0, $user->extra->username_change_count);
    }
}
