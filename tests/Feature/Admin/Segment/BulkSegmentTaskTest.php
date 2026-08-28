<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Segment;

use App\Enums\SegmentTaskStatus;
use App\Enums\SegmentTaskType;
use App\Jobs\Segment\BulkSegmentTaskJob;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\UserSegment\Membership;
use App\Models\UserSegment\Segment;
use App\Models\UserSegment\SegmentTask;
use App\Services\Segment\SegmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 异步分群任务 Job / Service 派发 / 通知测试
 */
#[CoversClass(BulkSegmentTaskJob::class)]
#[CoversClass(SegmentService::class)]
#[Group('admin')]
#[Group('segment')]
class BulkSegmentTaskTest extends TestCase
{
    use RefreshDatabase;

    private SegmentService $service;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
        $this->service = new SegmentService(new \App\Services\Segment\SegmentRuleEvaluator);
    }

    private function makeUser(int $points = 0): User
    {
        return User::factory()->create([
            'status' => 1,
            'available_points' => $points,
        ]);
    }

    // ============ Service::dispatchTask ============

    #[Test]
    #[TestDox('dispatchTask 创建 segment_tasks 记录并入队 Job')]
    public function dispatch_task_creates_record_and_dispatches_job(): void
    {
        Bus::fake();

        $seg = Segment::factory()->manual()->create();
        $userIds = [101, 102, 103];

        $task = $this->service->dispatchTask(
            adminId: $this->admin->id,
            type: SegmentTaskType::ATTACH,
            segmentIds: [$seg->id],
            userIds: $userIds,
        );

        $this->assertSame($this->admin->id, $task->admin_id);
        $this->assertSame(SegmentTaskType::ATTACH, $task->type);
        $this->assertSame(SegmentTaskStatus::PENDING, $task->status);
        $this->assertSame(3, $task->total_count);
        $this->assertSame(0, $task->processed_count);
        $this->assertSame([$seg->id], $task->payload['segment_ids']);
        $this->assertSame($userIds, $task->payload['user_ids']);

        Bus::assertDispatched(BulkSegmentTaskJob::class, function ($job) use ($task) {
            return $job->taskId === $task->id;
        });
    }

    #[Test]
    #[TestDox('shouldEnqueue 在任一维度 > 1000 时返回 true')]
    public function should_enqueue_triggers_on_size_limit(): void
    {
        $this->assertFalse($this->service->shouldEnqueue([1, 2, 3], [10, 20]));
        $this->assertFalse($this->service->shouldEnqueue([], []));

        // user_ids > 1000
        $this->assertTrue($this->service->shouldEnqueue(
            [1],
            range(1, SegmentService::BATCH_LIMIT + 1)
        ));
        // segment_ids > 1000
        $this->assertTrue($this->service->shouldEnqueue(
            range(1, SegmentService::BATCH_LIMIT + 1),
            [1, 2]
        ));
    }

    // ============ Job 同步执行（直接 handle） ============

    #[Test]
    #[TestDox('Job handle attach 模式：完成所有 attach 并更新进度')]
    public function job_handle_attach_completes(): void
    {
        $seg = Segment::factory()->manual()->create();
        $userIds = [];
        for ($i = 0; $i < 5; $i++) {
            $userIds[] = $this->makeUser()->id;
        }

        $task = $this->service->dispatchTask(
            adminId: $this->admin->id,
            type: SegmentTaskType::ATTACH,
            segmentIds: [$seg->id],
            userIds: $userIds,
        );

        // 同步执行（fake bus 不影响直接 handle）
        (new BulkSegmentTaskJob($task->id))->handle($this->service);

        $task->refresh();
        $this->assertSame(SegmentTaskStatus::COMPLETED, $task->status);
        $this->assertSame(5, $task->processed_count);
        $this->assertSame(5, $task->added_count);
        $this->assertSame(100, $task->progressPercent());
        $this->assertNotNull($task->started_at);
        $this->assertNotNull($task->finished_at);

        // memberships 实际写入
        $this->assertSame(5, Membership::where('segment_id', $seg->id)->count());
    }

    #[Test]
    #[TestDox('Job handle detach 模式：移除所有 memberships')]
    public function job_handle_detach_completes(): void
    {
        $seg = Segment::factory()->manual()->create();
        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $users[] = $this->makeUser();
        }
        $this->service->attachUsers($seg, array_map(fn ($u) => $u->id, $users));
        $this->assertSame(3, Membership::where('segment_id', $seg->id)->count());

        $task = $this->service->dispatchTask(
            adminId: $this->admin->id,
            type: SegmentTaskType::DETACH,
            segmentIds: [$seg->id],
            userIds: array_map(fn ($u) => $u->id, $users),
        );

        (new BulkSegmentTaskJob($task->id))->handle($this->service);

        $task->refresh();
        $this->assertSame(SegmentTaskStatus::COMPLETED, $task->status);
        $this->assertSame(0, Membership::where('segment_id', $seg->id)->count());
    }

    #[Test]
    #[TestDox('Job handle 跳过不存在的 segment_id 但仍标记完成')]
    public function job_handle_skips_missing_segments(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u = $this->makeUser();

        $task = $this->service->dispatchTask(
            adminId: $this->admin->id,
            type: SegmentTaskType::ATTACH,
            segmentIds: [$seg->id, 99999],  // 99999 不存在
            userIds: [$u->id],
        );

        (new BulkSegmentTaskJob($task->id))->handle($this->service);

        $task->refresh();
        $this->assertSame(SegmentTaskStatus::COMPLETED, $task->status);
        // 只对存在的 segment 执行
        $this->assertSame(1, Membership::where('segment_id', $seg->id)->count());
    }

    #[Test]
    #[TestDox('Job 重复执行幂等：running 状态直接返回')]
    public function job_handle_is_idempotent_on_rerun(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u = $this->makeUser();
        $task = $this->service->dispatchTask(
            adminId: $this->admin->id,
            type: SegmentTaskType::ATTACH,
            segmentIds: [$seg->id],
            userIds: [$u->id],
        );

        // 第一次执行
        (new BulkSegmentTaskJob($task->id))->handle($this->service);
        $task->refresh();
        $firstCount = Membership::where('segment_id', $seg->id)->count();
        $this->assertSame(1, $firstCount);

        // 第二次执行（同 job 重试模拟）：应直接 return
        (new BulkSegmentTaskJob($task->id))->handle($this->service);
        $this->assertSame(1, Membership::where('segment_id', $seg->id)->count());
    }

    #[Test]
    #[TestDox('Job 成功完成时只推送完成通知，不推送失败通知')]
    public function job_handle_success_sends_only_completion_notification(): void
    {
        Notification::fake();

        $seg = Segment::factory()->manual()->create();
        $u = $this->makeUser();
        $task = $this->service->dispatchTask(
            adminId: $this->admin->id,
            type: SegmentTaskType::ATTACH,
            segmentIds: [$seg->id],
            userIds: [$u->id],
        );

        (new BulkSegmentTaskJob($task->id))->handle($this->service);

        // 成功路径：推完成通知，不推失败通知
        Notification::assertSentTo($this->admin, \App\Notifications\Segment\SegmentTaskCompletedNotification::class);
        Notification::assertNotSentTo($this->admin, \App\Notifications\Segment\SegmentTaskFailedNotification::class);
    }

    // ============ Controller 端到端：自动入队 ============

    #[Test]
    #[TestDox('attach > 1000 自动入队返回 202 + task_id')]
    public function controller_attach_over_limit_returns_202(): void
    {
        Bus::fake();

        $seg = Segment::factory()->manual()->create();
        $userIds = range(1, SegmentService::BATCH_LIMIT + 5);

        $r = $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/user-segments/{$seg->id}/attach", ['user_ids' => $userIds]);

        $r->assertStatus(202)
            ->assertJsonPath('mode', 'async')
            ->assertJsonPath('status', 'pending');

        $taskId = $r->json('task_id');
        $this->assertIsInt($taskId);

        $task = SegmentTask::query()->findOrFail($taskId);
        $this->assertSame($this->admin->id, $task->admin_id);
        $this->assertSame(SegmentTaskType::ATTACH, $task->type);
        $this->assertSame(count($userIds), $task->total_count);

        Bus::assertDispatched(BulkSegmentTaskJob::class);
    }

    #[Test]
    #[TestDox('batch_attach 任一维度 > 1000 自动入队')]
    public function controller_batch_attach_over_limit_returns_202(): void
    {
        Bus::fake();

        $s1 = Segment::factory()->manual()->create();
        $s2 = Segment::factory()->manual()->create();
        $userIds = range(1, SegmentService::BATCH_LIMIT + 1);

        $r = $this->actingAs($this->admin, 'admin')
            ->postJson('/admin/user-segments/batch-attach', [
                'segment_ids' => [$s1->id, $s2->id],
                'user_ids' => $userIds,
            ]);

        $r->assertStatus(202)
            ->assertJsonPath('mode', 'async')
            ->assertJsonPath('requested', 2 * count($userIds));
    }

    #[Test]
    #[TestDox('segment-tasks 列表：管理员只能看到自己的任务')]
    public function segment_tasks_index_filters_by_admin(): void
    {
        $other = Admin::query()->create([
            'username' => 'other_admin',
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
        ]);
        SegmentTask::factory()->create(['admin_id' => $this->admin->id]);
        SegmentTask::factory()->create(['admin_id' => $other->id]);

        $r = $this->actingAs($this->admin, 'admin')->getJson('/admin/segment-tasks');
        $r->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('segment-tasks/{task} 详情：自己可见，他人不可见')]
    public function segment_tasks_show_only_owner(): void
    {
        $other = Admin::query()->create([
            'username' => 'other_admin',
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
        ]);
        $myTask = SegmentTask::factory()->create(['admin_id' => $this->admin->id]);
        $hisTask = SegmentTask::factory()->create(['admin_id' => $other->id]);

        // 我访问自己的 OK
        $this->actingAs($this->admin, 'admin')
            ->getJson("/admin/segment-tasks/{$myTask->id}")
            ->assertOk();

        // 我访问他人的 404
        $this->actingAs($this->admin, 'admin')
            ->getJson("/admin/segment-tasks/{$hisTask->id}")
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('SegmentTask::progressPercent 计算正确（含 total_count=0 边界）')]
    public function progress_percent_calculation(): void
    {
        $task = SegmentTask::factory()->create([
            'total_count' => 200,
            'processed_count' => 50,
        ]);
        $this->assertSame(25, $task->progressPercent());

        $task2 = SegmentTask::factory()->create(['total_count' => 0]);
        $this->assertSame(0, $task2->progressPercent());
    }
}
