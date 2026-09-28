<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\AgreementController;
use App\Models\Operation\Agreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 前台公开协议接口功能测试
 */
#[CoversClass(AgreementController::class)]
#[Group('api')]
#[Group('agreement')]
class AgreementControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('按类别获取最新已发布协议')]
    public function guest_can_get_published_agreement_by_category(): void
    {
        $agreement = Agreement::factory()->published()->create([
            'category' => 'user_agreement',
            'title' => '用户协议 v2.0',
            'version' => 'v2.0',
        ]);

        // 创建一个旧版本
        Agreement::factory()->published()->create([
            'category' => 'user_agreement',
            'title' => '用户协议 v1.0',
            'version' => 'v1.0',
            'effective_at' => now()->subDays(30),
        ]);

        $response = $this->getJson('/api/v1/agreements/user_agreement');

        $response->assertOk()
            ->assertJsonPath('title', '用户协议 v2.0')
            ->assertJsonPath('version', 'v2.0')
            ->assertJsonStructure(['id', 'title', 'category', 'content', 'version', 'effective_at']);
    }

    #[Test]
    #[TestDox('协议不存在返回 404')]
    public function agreement_not_found_returns_404(): void
    {
        $this->getJson('/api/v1/agreements/non_existent_category')
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('草稿协议不会被返回')]
    public function draft_agreement_not_returned(): void
    {
        Agreement::factory()->draft()->create([
            'category' => 'privacy_policy',
        ]);

        $this->getJson('/api/v1/agreements/privacy_policy')
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('归档协议不会被返回')]
    public function archived_agreement_not_returned(): void
    {
        Agreement::factory()->archived()->create([
            'category' => 'recharge_agreement',
        ]);

        $this->getJson('/api/v1/agreements/recharge_agreement')
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('返回最新生效版本的协议')]
    public function returns_latest_effective_version(): void
    {
        // 旧版本
        Agreement::factory()->published()->create([
            'category' => 'user_agreement',
            'version' => 'v1.0',
            'effective_at' => now()->subDays(60),
        ]);

        // 中间版本
        Agreement::factory()->published()->create([
            'category' => 'user_agreement',
            'version' => 'v2.0',
            'effective_at' => now()->subDays(30),
        ]);

        // 最新版本
        Agreement::factory()->published()->create([
            'category' => 'user_agreement',
            'version' => 'v3.0',
            'effective_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/agreements/user_agreement');

        $response->assertOk()
            ->assertJsonPath('version', 'v3.0');
    }

    #[Test]
    #[TestDox('响应包含 content 字段')]
    public function response_includes_content(): void
    {
        Agreement::factory()->published()->create([
            'category' => 'user_agreement',
        ]);

        $response = $this->getJson('/api/v1/agreements/user_agreement');

        $response->assertOk()
            ->assertJsonStructure(['content']);
    }
}
