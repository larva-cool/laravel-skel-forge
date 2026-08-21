<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\User\DeleteAccessTokenJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * DeleteAccessTokenJob 单元测试
 */
#[CoversClass(DeleteAccessTokenJob::class)]
#[Group('jobs')]
class DeleteAccessTokenJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('构造函数正确赋值 token 属性')]
    public function constructor_sets_token_property(): void
    {
        $job = new DeleteAccessTokenJob('some-token-hash');

        $this->assertSame('some-token-hash', $job->token);
    }

    #[Test]
    #[TestDox('handle 删除指定 token 记录')]
    public function handle_deletes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['user']);
        $tokenHash = $token->accessToken->token;

        $this->assertDatabaseHas('personal_access_tokens', ['token' => $tokenHash]);

        $job = new DeleteAccessTokenJob($tokenHash);
        $job->handle();

        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $tokenHash]);
    }

    #[Test]
    #[TestDox('handle 不存在的 token 不报错')]
    public function handle_does_not_throw_when_token_not_found(): void
    {
        $job = new DeleteAccessTokenJob('non-existent-token');

        $job->handle();

        $this->assertTrue(true); // 没有异常即通过
    }

    #[Test]
    #[TestDox('handle 只删除指定 token 不影响其他 token')]
    public function handle_only_deletes_specified_token(): void
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('token-1', ['user']);
        $token2 = $user->createToken('token-2', ['user']);

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $job = new DeleteAccessTokenJob($token1->accessToken->token);
        $job->handle();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['token' => $token2->accessToken->token]);
    }
}
