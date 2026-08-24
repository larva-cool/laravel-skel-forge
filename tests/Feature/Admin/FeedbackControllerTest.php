<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\FeedbackController;
use App\Models\Admin\Admin;
use Database\Factories\FeedbackFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台反馈管理控制器功能测试
 */
#[CoversClass(FeedbackController::class)]
#[Group('admin')]
#[Group('feedback')]
class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问反馈列表返回 401')]
    public function guest_cannot_list_feedbacks(): void
    {
        $this->getJson('/admin/feedbacks')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取反馈列表返回 200 与分页数据')]
    public function admin_can_list_feedbacks(): void
    {
        FeedbackFactory::new()->count(3)->create();

        $response = $this->actingAsAdmin()->getJson('/admin/feedbacks');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('列表返回数据包含类型/状态/优先级标签字段')]
    public function list_response_contains_label_fields(): void
    {
        FeedbackFactory::new()->create(['type' => 'bug', 'status' => 0, 'priority' => 3]);

        $response = $this->actingAsAdmin()->getJson('/admin/feedbacks');

        $data = $response->json('data.0');
        $this->assertArrayHasKey('type_label', $data);
        $this->assertArrayHasKey('type_label_type', $data);
        $this->assertArrayHasKey('status_label', $data);
        $this->assertArrayHasKey('priority_label', $data);
        $this->assertArrayHasKey('images', $data);
        $this->assertIsArray($data['images']);
    }

    #[Test]
    #[TestDox('详情返回反馈图片')]
    public function detail_returns_images_array(): void
    {
        $images = ['https://cdn.example.com/a.jpg', 'https://cdn.example.com/b.jpg'];
        $feedback = FeedbackFactory::new()->create(['images' => $images]);

        $response = $this->actingAsAdmin()->getJson("/admin/feedbacks/{$feedback->id}");

        $response->assertOk()
            ->assertJsonPath('images', $images);
    }

    #[Test]
    #[TestDox('按关键词搜索反馈')]
    public function admin_can_search_feedbacks_by_keyword(): void
    {
        FeedbackFactory::new()->create(['title' => '菜单管理体验问题']);
        FeedbackFactory::new()->create(['title' => '其他标题']);

        $response = $this->actingAsAdmin()->getJson('/admin/feedbacks?keyword='.rawurlencode('菜单'));

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按类型筛选反馈')]
    public function admin_can_filter_by_type(): void
    {
        FeedbackFactory::new()->create(['type' => 'bug']);
        FeedbackFactory::new()->create(['type' => 'feature']);

        $response = $this->actingAsAdmin()->getJson('/admin/feedbacks?type=bug');

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按状态筛选反馈')]
    public function admin_can_filter_by_status(): void
    {
        FeedbackFactory::new()->create(['status' => 0]);
        FeedbackFactory::new()->resolved()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/feedbacks?status=4');

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('获取反馈统计数据')]
    public function admin_can_get_stats(): void
    {
        FeedbackFactory::new()->count(2)->create(['status' => 0]);
        FeedbackFactory::new()->count(3)->resolved()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/feedbacks/stats');

        $response->assertOk()
            ->assertJsonPath('total', 5)
            ->assertJsonPath('pending', 2)
            ->assertJsonPath('closed', 3);
    }

    #[Test]
    #[TestDox('获取反馈详情包含完整内容')]
    public function admin_can_view_feedback_detail(): void
    {
        $feedback = FeedbackFactory::new()->create(['content' => '详细描述内容']);

        $response = $this->actingAsAdmin()->getJson("/admin/feedbacks/{$feedback->id}");

        $response->assertOk()
            ->assertJsonPath('id', $feedback->id)
            ->assertJsonPath('content', '详细描述内容');
    }

    #[Test]
    #[TestDox('管理员可更新反馈状态，首次处理自动记录处理人')]
    public function admin_can_update_status_and_record_admin(): void
    {
        $feedback = FeedbackFactory::new()->create(['status' => 0]);

        $response = $this->actingAsAdmin()->putJson("/admin/feedbacks/{$feedback->id}", [
            'status' => 3,
            'admin_remark' => '正在处理中',
        ]);

        $response->assertOk();
        $feedback->refresh();
        $this->assertSame(3, $feedback->status->value);
        $this->assertSame($this->admin->id, $feedback->admin_id);
        $this->assertSame('正在处理中', $feedback->admin_remark);
    }

    #[Test]
    #[TestDox('将反馈标记为已解决时自动记录解决时间')]
    public function resolving_feedback_sets_resolved_at(): void
    {
        $feedback = FeedbackFactory::new()->create(['status' => 0]);

        $this->actingAsAdmin()->putJson("/admin/feedbacks/{$feedback->id}", [
            'status' => 4,
        ]);

        $feedback->refresh();
        $this->assertNotNull($feedback->resolved_at);
    }

    #[Test]
    #[TestDox('将反馈标记为已关闭时自动记录关闭时间')]
    public function closing_feedback_sets_closed_at(): void
    {
        $feedback = FeedbackFactory::new()->create(['status' => 0]);

        $this->actingAsAdmin()->putJson("/admin/feedbacks/{$feedback->id}", [
            'status' => 5,
        ]);

        $feedback->refresh();
        $this->assertNotNull($feedback->closed_at);
    }

    #[Test]
    #[TestDox('更新无效状态返回验证错误')]
    public function invalid_status_returns_validation_error(): void
    {
        $feedback = FeedbackFactory::new()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/feedbacks/{$feedback->id}", [
            'status' => 99,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    #[TestDox('管理员可删除反馈')]
    public function admin_can_delete_feedback(): void
    {
        $feedback = FeedbackFactory::new()->create();

        $this->actingAsAdmin()->deleteJson("/admin/feedbacks/{$feedback->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('feedbacks', ['id' => $feedback->id]);
    }

    #[Test]
    #[TestDox('按字典编码获取启用的字典项')]
    public function admin_can_get_dict_items_by_code(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/dicts/by-code/feedback_type/items');

        $response->assertOk();
        // 默认应包含 4 个反馈类型（单资源列表无 data 包装，直接返回数组）
        $this->assertGreaterThanOrEqual(4, count($response->json()));
    }
}
