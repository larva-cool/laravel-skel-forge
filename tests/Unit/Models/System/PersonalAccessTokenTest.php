<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Models\System\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * PersonalAccessToken 模型单元测试
 */
#[CoversClass(PersonalAccessToken::class)]
#[Group('models')]
#[Group('personal-access-token')]
class PersonalAccessTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('继承自 Sanctum PersonalAccessToken')]
    public function extends_sanctum_personal_access_token(): void
    {
        $this->assertTrue(is_subclass_of(PersonalAccessToken::class, \Laravel\Sanctum\PersonalAccessToken::class));
    }

    #[Test]
    #[TestDox('使用 DateTimeFormatter trait')]
    public function uses_date_time_formatter_trait(): void
    {
        $this->assertContains(
            \App\Models\Traits\DateTimeFormatter::class,
            class_uses(PersonalAccessToken::class)
        );
    }

    #[Test]
    #[TestDox('tokenable 关系返回关联的用户')]
    public function tokenable_returns_related_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['user']);

        $this->assertInstanceOf(User::class, $token->accessToken->tokenable);
        $this->assertSame($user->id, $token->accessToken->tokenable->id);
    }

    #[Test]
    #[TestDox('abilities 正确 cast 为 array')]
    public function abilities_is_cast_to_array(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['read', 'write']);

        $this->assertIsArray($token->accessToken->abilities);
        $this->assertContains('read', $token->accessToken->abilities);
        $this->assertContains('write', $token->accessToken->abilities);
    }

    #[Test]
    #[TestDox('expires_at 正确 cast 为 datetime')]
    public function expires_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['user']);
        $token->accessToken->update(['expires_at' => '2025-12-31 23:59:59']);
        $token->accessToken->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $token->accessToken->expires_at);
    }

    #[Test]
    #[TestDox('last_used_at 正确 cast 为 datetime')]
    public function last_used_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['user']);
        $token->accessToken->forceFill(['last_used_at' => '2025-01-01 10:00:00'])->save();
        $token->accessToken->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $token->accessToken->last_used_at);
    }
}
