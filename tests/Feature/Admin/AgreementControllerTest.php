<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AgreementStatus;
use App\Http\Controllers\Admin\AgreementController;
use App\Models\Admin\Admin;
use App\Models\Operation\Agreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台协议管理控制器功能测试
 */
#[CoversClass(AgreementController::class)]
#[Group('admin')]
#[Group('agreement')]
class AgreementControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 默认有效 payload
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => '用户协议',
            'category' => 'user_agreement',
            'content' => '<p>欢迎使用本服务，请仔细阅读以下协议内容。</p>',
            'version' => 'v1.0',
            'status' => AgreementStatus::DRAFT->value,
            'sort' => 100,
        ], $overrides);
    }

    #[Test]
    #[TestDox('未登录访问协议列表返回 401')]
    public function guest_cannot_list_agreements(): void
    {
        $this->getJson('/admin/agreements')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取协议列表分页数据')]
    public function admin_can_list_agreements(): void
    {
        Agreement::factory()->count(3)->create();

        $response = $this->actingAsAdmin()->getJson('/admin/agreements');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按状态筛选协议')]
    public function admin_can_filter_by_status(): void
    {
        Agreement::factory()->count(2)->draft()->create();
        Agreement::factory()->count(3)->published()->create();
        Agreement::factory()->count(2)->archived()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/agreements?status='.AgreementStatus::PUBLISHED->value);

        $response->assertOk()->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按类别筛选协议')]
    public function admin_can_filter_by_category(): void
    {
        Agreement::factory()->count(2)->create(['category' => 'user_agreement']);
        Agreement::factory()->count(3)->create(['category' => 'privacy_policy']);

        $response = $this->actingAsAdmin()->getJson('/admin/agreements?category=privacy_policy');

        $response->assertOk()->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按关键词搜索协议标题')]
    public function admin_can_search_by_keyword(): void
    {
        Agreement::factory()->create(['title' => '用户服务协议']);
        Agreement::factory()->create(['title' => '隐私政策']);

        $response = $this->actingAsAdmin()->getJson('/admin/agreements?keyword='.rawurlencode('用户'));

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('列表不返回 content 字段')]
    public function list_response_excludes_content(): void
    {
        Agreement::factory()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/agreements');
        $data = $response->json('data.0');

        $this->assertArrayNotHasKey('content', $data);
    }

    #[Test]
    #[TestDox('详情返回 content 字段')]
    public function show_response_includes_content(): void
    {
        $agreement = Agreement::factory()->create();

        $response = $this->actingAsAdmin()->getJson("/admin/agreements/{$agreement->id}");

        $response->assertOk()
            ->assertJsonPath('id', $agreement->id)
            ->assertJsonStructure(['content']);
    }

    #[Test]
    #[TestDox('管理员可创建协议')]
    public function admin_can_create_agreement(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/agreements', $this->validPayload([
            'title' => '充值协议',
            'category' => 'recharge_agreement',
        ]));

        $response->assertCreated()
            ->assertJsonPath('title', '充值协议')
            ->assertJsonPath('status', AgreementStatus::DRAFT->value);

        $this->assertDatabaseHas('agreements', [
            'title' => '充值协议',
            'category' => 'recharge_agreement',
            'status' => AgreementStatus::DRAFT->value,
        ]);
    }

    #[Test]
    #[TestDox('创建协议时直接设置 published 状态自动写入 effective_at')]
    public function create_with_published_status_sets_effective_at(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/agreements', $this->validPayload([
            'status' => AgreementStatus::PUBLISHED->value,
        ]));

        $response->assertCreated();

        $this->assertNotNull($response->json('effective_at'));
    }

    #[Test]
    #[TestDox('创建协议时缺少 title 字段返回 422')]
    public function create_requires_title(): void
    {
        $payload = $this->validPayload();
        unset($payload['title']);

        $this->actingAsAdmin()->postJson('/admin/agreements', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    #[TestDox('创建协议时缺少 content 字段返回 422')]
    public function create_requires_content(): void
    {
        $payload = $this->validPayload();
        unset($payload['content']);

        $this->actingAsAdmin()->postJson('/admin/agreements', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    #[Test]
    #[TestDox('创建协议时 status 非法返回 422')]
    public function create_validates_status_enum(): void
    {
        $this->actingAsAdmin()->postJson('/admin/agreements', $this->validPayload([
            'status' => 'invalid_status',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    #[TestDox('管理员可更新协议')]
    public function admin_can_update_agreement(): void
    {
        $agreement = Agreement::factory()->create(['title' => '原标题']);

        $response = $this->actingAsAdmin()->putJson("/admin/agreements/{$agreement->id}", $this->validPayload([
            'title' => '更新后的标题',
            'sort' => 99,
        ]));

        $response->assertOk()
            ->assertJsonPath('title', '更新后的标题')
            ->assertJsonPath('sort', 99);

        $agreement->refresh();
        $this->assertSame('更新后的标题', $agreement->title);
        $this->assertSame(99, $agreement->sort);
    }

    #[Test]
    #[TestDox('切换协议状态：草稿 → 已发布')]
    public function toggle_status_from_draft_to_published(): void
    {
        $agreement = Agreement::factory()->draft()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/agreements/{$agreement->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('status', AgreementStatus::PUBLISHED->value);

        $agreement->refresh();
        $this->assertSame(AgreementStatus::PUBLISHED, $agreement->status);
        $this->assertNotNull($agreement->effective_at);
    }

    #[Test]
    #[TestDox('切换协议状态：已发布 → 已归档')]
    public function toggle_status_from_published_to_archived(): void
    {
        $agreement = Agreement::factory()->published()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/agreements/{$agreement->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('status', AgreementStatus::ARCHIVED->value);

        $agreement->refresh();
        $this->assertSame(AgreementStatus::ARCHIVED, $agreement->status);
    }

    #[Test]
    #[TestDox('切换协议状态：已归档 → 已发布')]
    public function toggle_status_from_archived_to_published(): void
    {
        $agreement = Agreement::factory()->archived()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/agreements/{$agreement->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('status', AgreementStatus::PUBLISHED->value);

        $agreement->refresh();
        $this->assertSame(AgreementStatus::PUBLISHED, $agreement->status);
    }

    #[Test]
    #[TestDox('管理员可删除协议')]
    public function admin_can_delete_agreement(): void
    {
        $agreement = Agreement::factory()->create();

        $this->actingAsAdmin()->deleteJson("/admin/agreements/{$agreement->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('agreements', ['id' => $agreement->id]);
    }

    #[Test]
    #[TestDox('查看不存在协议返回 404')]
    public function view_missing_agreement_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/agreements/999999')
            ->assertNotFound();
    }
}
