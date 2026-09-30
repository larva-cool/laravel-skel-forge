<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ReportController;
use App\Models\Admin\Admin;
use App\Models\Content\Comment;
use App\Models\User as UserModel;
use Database\Factories\Content\ReportFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台举报管理 - 列表/详情/统计功能测试
 */
#[CoversClass(ReportController::class)]
#[Group('admin')]
#[Group('report')]
class ReportControllerIndexTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 创建一条用于被举报的评论（带 user_id 与 source）
     */
    private function makeComment(): Comment
    {
        $source = UserModel::factory()->create();

        return Comment::factory()->forSource($source)->create([
            'user_id' => UserModel::factory()->create()->id,
        ]);
    }

    #[Test]
    #[TestDox('未登录访问举报列表返回 401')]
    public function guest_cannot_list_reports(): void
    {
        $this->getJson('/admin/reports')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取举报列表分页数据')]
    public function admin_can_list_reports(): void
    {
        $comment = $this->makeComment();
        ReportFactory::new()->forTarget($comment)->count(3)->create();

        $response = $this->actingAsAdmin()->getJson('/admin/reports');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('列表项返回 reportable_type_label、status_label、category_label 等渲染字段')]
    public function list_items_contain_label_fields(): void
    {
        $comment = $this->makeComment();
        ReportFactory::new()->forTarget($comment)->create([
            'category' => 'ad',
            'status' => 'pending',
        ]);

        $response = $this->actingAsAdmin()->getJson('/admin/reports');
        $item = $response->json('data.0');

        $this->assertSame('ad', $item['category']);
        $this->assertSame('pending', $item['status']);
        $this->assertArrayHasKey('category_label', $item);
        $this->assertArrayHasKey('category_label_type', $item);
        $this->assertArrayHasKey('status_label', $item);
        $this->assertArrayHasKey('status_label_type', $item);
        $this->assertArrayHasKey('reportable_type', $item);
        $this->assertArrayHasKey('reportable_type_label', $item);
    }

    #[Test]
    #[TestDox('列表不返回 reason/evidence/process_actions 详情字段')]
    public function list_items_exclude_detail_fields(): void
    {
        $comment = $this->makeComment();
        ReportFactory::new()->forTarget($comment)->create([
            'reason' => '仅详情可见的内容',
            'evidence' => ['https://cdn.example.com/x.jpg'],
        ]);

        $item = $this->actingAsAdmin()->getJson('/admin/reports')->json('data.0');

        $this->assertArrayNotHasKey('reason', $item);
        $this->assertArrayNotHasKey('evidence', $item);
        $this->assertArrayNotHasKey('process_actions', $item);
        $this->assertArrayNotHasKey('reportable_snapshot', $item);
    }

    #[Test]
    #[TestDox('详情返回 reason/evidence/process_actions/reportable_snapshot')]
    public function detail_returns_extended_fields(): void
    {
        $comment = $this->makeComment();
        $report = ReportFactory::new()->forTarget($comment)->create([
            'reason' => '举报详情原因',
            'evidence' => ['https://cdn.example.com/a.jpg'],
            'process_actions' => [['type' => 'hide_comment']],
        ]);

        $response = $this->actingAsAdmin()->getJson("/admin/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('reason', '举报详情原因')
            ->assertJsonPath('evidence.0', 'https://cdn.example.com/a.jpg')
            ->assertJsonPath('process_actions.0.type', 'hide_comment')
            ->assertJsonStructure(['reportable_snapshot' => ['type', 'id']]);
    }

    #[Test]
    #[TestDox('按状态筛选 pending 状态举报')]
    public function filter_by_status(): void
    {
        $comment = $this->makeComment();
        ReportFactory::new()->forTarget($comment)->create(['status' => 'pending']);
        ReportFactory::new()->forTarget($comment)->create(['status' => 'resolved']);

        $response = $this->actingAsAdmin()->getJson('/admin/reports?status=pending');
        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按分类筛选')]
    public function filter_by_category(): void
    {
        $comment = $this->makeComment();
        ReportFactory::new()->forTarget($comment)->create(['category' => 'ad']);
        ReportFactory::new()->forTarget($comment)->create(['category' => 'insult']);

        $response = $this->actingAsAdmin()->getJson('/admin/reports?category=ad');
        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按 reportable_type 筛选')]
    public function filter_by_reportable_type(): void
    {
        $comment = $this->makeComment();
        $user = UserModel::factory()->create();

        ReportFactory::new()->forTarget($comment)->create();
        ReportFactory::new()->forTarget($user)->create();

        $response = $this->actingAsAdmin()->getJson('/admin/reports?reportable_type='.rawurlencode($comment->getMorphClass()));

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按关键词搜索 reason 或 user username')]
    public function search_by_keyword(): void
    {
        $comment = $this->makeComment();
        $reporter = UserModel::factory()->create(['username' => 'spammer_user']);
        ReportFactory::new()->forTarget($comment)->create([
            'user_id' => $reporter->id,
            'reason' => '该用户发广告',
        ]);
        ReportFactory::new()->forTarget($comment)->create([
            'reason' => '其他无关内容',
        ]);

        $response = $this->actingAsAdmin()->getJson('/admin/reports?keyword='.rawurlencode('广告'));
        $response->assertOk()->assertJsonPath('meta.total', 1);

        $response = $this->actingAsAdmin()->getJson('/admin/reports?keyword='.rawurlencode('spammer_user'));
        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按日期范围筛选')]
    public function filter_by_date_range(): void
    {
        $comment = $this->makeComment();
        $today = now()->toDateString();
        ReportFactory::new()->forTarget($comment)->create(['created_at' => now()]);

        $response = $this->actingAsAdmin()->getJson("/admin/reports?start_date={$today}&end_date={$today}");
        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('举报统计接口返回 total/pending/today/closed')]
    public function stats_returns_four_fields(): void
    {
        $comment = $this->makeComment();
        // 历史数据（非今日 created_at）
        ReportFactory::new()->forTarget($comment)->count(2)->create([
            'status' => 'pending',
            'created_at' => now()->subDays(3),
        ]);
        ReportFactory::new()->forTarget($comment)->count(3)->create([
            'status' => 'resolved',
            'created_at' => now()->subDays(2),
        ]);
        ReportFactory::new()->forTarget($comment)->count(1)->create([
            'status' => 'rejected',
            'created_at' => now()->subDays(1),
        ]);
        // 今日新增 pending
        ReportFactory::new()->forTarget($comment)->count(1)->create([
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $response = $this->actingAsAdmin()->getJson('/admin/reports/stats');

        $response->assertOk()
            ->assertJsonPath('total', 7)
            ->assertJsonPath('pending', 3)
            ->assertJsonPath('today', 1)
            ->assertJsonPath('closed', 4);
    }

    #[Test]
    #[TestDox('举报详情快照按 reportable 类型返回（comment 含 content）')]
    public function detail_snapshot_for_comment_contains_content(): void
    {
        $comment = $this->makeComment();
        $comment->update(['content' => '违规评论内容']);
        $report = ReportFactory::new()->forTarget($comment)->create();

        $response = $this->actingAsAdmin()->getJson("/admin/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('reportable_snapshot.type', 'comment')
            ->assertJsonPath('reportable_snapshot.content', '违规评论内容');
    }

    #[Test]
    #[TestDox('举报详情快照按 user 类型返回（user 含 username）')]
    public function detail_snapshot_for_user_contains_username(): void
    {
        $target = UserModel::factory()->create(['username' => 'spammer']);
        $report = ReportFactory::new()->forTarget($target)->create();

        $response = $this->actingAsAdmin()->getJson("/admin/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('reportable_snapshot.type', 'user')
            ->assertJsonPath('reportable_snapshot.username', 'spammer');
    }

    #[Test]
    #[TestDox('被举报对象已删除时 snapshot 返回 null')]
    public function detail_snapshot_null_when_target_deleted(): void
    {
        $comment = $this->makeComment();
        $report = ReportFactory::new()->forTarget($comment)->create();
        $comment->delete();

        $response = $this->actingAsAdmin()->getJson("/admin/reports/{$report->id}");

        $response->assertOk()->assertJsonPath('reportable_snapshot', null);
    }
}
