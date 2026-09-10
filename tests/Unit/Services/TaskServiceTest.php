<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\Gender;
use App\Enums\Task\TaskProgressStatus;
use App\Enums\Task\TaskRewardType;
use App\Models\Task\Task;
use App\Models\Task\TaskGroup;
use App\Models\Task\TaskProgress;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 任务服务单元测试
 */
#[CoversClass(TaskService::class)]
#[Group('services')]
#[Group('task')]
class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaskService $taskService;

    private User $user;

    private TaskGroup $newbieGroup;

    private Task $completeProfileTask;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taskService = new TaskService;

        // 创建用户（UserObserver 自动创建空的 user_profile 和 user_extra）
        // 显式设置 name=null, avatar=null 以模拟真实的新注册用户状态
        $this->user = User::factory()->create([
            'name' => null,
            'avatar' => null,
        ]);
        $this->user->loadMissing('profile');

        // 种子迁移（seed_initial_task_data）已创建新手任务组和完善资料任务，直接获取
        $this->newbieGroup = TaskGroup::where('code', 'newbie')->firstOrFail();
        $this->completeProfileTask = Task::where('code', 'complete_profile')->firstOrFail();
    }

    #[Test]
    #[TestDox('新注册用户资料完整度为 0')]
    public function profile_completeness_for_new_user_is_zero(): void
    {
        // 新用户：username 有值但 name=null, avatar=null, gender=UNKNOWN, birthday=null, bio=null, province_id=null
        $completeness = $this->taskService->calculateProfileCompleteness($this->user);
        $this->assertSame(0, $completeness);
    }

    #[Test]
    #[TestDox('填一项资料后完整度为 17%（1/6）')]
    public function profile_completeness_with_one_filled_field(): void
    {
        $this->user->update(['name' => '测试昵称']);
        $this->user->refresh()->loadMissing('profile');

        $completeness = $this->taskService->calculateProfileCompleteness($this->user);
        $this->assertSame(17, $completeness); // round(1/6 * 100) = 17
    }

    #[Test]
    #[TestDox('填 3 项资料后完整度为 50%')]
    public function profile_completeness_with_three_filled_fields(): void
    {
        $this->user->update(['name' => '测试昵称', 'avatar' => 'avatar.jpg']);
        $this->user->profile->update([
            'gender' => Gender::GENDER_MALE,
        ]);
        $this->user->refresh()->loadMissing('profile');

        $completeness = $this->taskService->calculateProfileCompleteness($this->user);
        $this->assertSame(50, $completeness); // 3/6 = 50%
    }

    #[Test]
    #[TestDox('全部资料填完后完整度为 100%')]
    public function profile_completeness_with_all_fields_filled(): void
    {
        $this->user->update(['name' => '测试昵称', 'avatar' => 'avatar.jpg']);
        $this->user->profile->update([
            'gender' => Gender::GENDER_FEMALE,
            'birthday' => '2000-01-01',
            'bio' => '这是我的个性签名',
            'province_id' => 1,
            'city_id' => 1,
            'district_id' => 1,
        ]);
        $this->user->refresh()->loadMissing('profile');

        $completeness = $this->taskService->calculateProfileCompleteness($this->user);
        $this->assertSame(100, $completeness);
    }

    #[Test]
    #[TestDox('性别为保密（0）不算填写')]
    public function profile_completeness_unknown_gender_not_counted(): void
    {
        $this->user->update(['name' => '测试昵称']);
        // gender 保持默认 GENDER_UNKNOWN(0)
        $this->user->refresh()->loadMissing('profile');

        $completeness = $this->taskService->calculateProfileCompleteness($this->user);
        $this->assertSame(17, $completeness);
    }

    #[Test]
    #[TestDox('地区只填省/市但不填区县不算完成地区')]
    public function profile_completeness_partial_region_not_counted(): void
    {
        $this->user->profile->update([
            'province_id' => 1,
            'city_id' => 1,
            // district_id 未填
        ]);
        $this->user->refresh()->loadMissing('profile');

        $completeness = $this->taskService->calculateProfileCompleteness($this->user);
        $this->assertSame(0, $completeness); // 地区没算填完，0/6
    }

    #[Test]
    #[TestDox('签名为空字符串不算填写')]
    public function profile_completeness_empty_bio_not_counted(): void
    {
        $this->user->profile->update(['bio' => '']);
        $this->user->refresh()->loadMissing('profile');

        $completeness = $this->taskService->calculateProfileCompleteness($this->user);
        $this->assertSame(0, $completeness);
    }

    #[Test]
    #[TestDox('更新资料完整度未达阈值时进度不完成')]
    public function update_profile_completeness_below_threshold_no_completion(): void
    {
        $this->taskService->updateProfileCompleteness($this->user, 50);

        $progress = TaskProgress::where('user_id', $this->user->id)
            ->where('task_id', $this->completeProfileTask->id)
            ->where('period_key', 'all')
            ->first();

        $this->assertNotNull($progress);
        $this->assertSame(50, $progress->current_value);
        $this->assertSame(TaskProgressStatus::IN_PROGRESS, $progress->status);
        $this->assertNull($progress->completed_at);
    }

    #[Test]
    #[TestDox('更新资料完整度达到 100% 后自动完成任务并发放奖励')]
    public function update_profile_completeness_reaches_threshold_completes_task_and_grants_reward(): void
    {
        $results = $this->taskService->updateProfileCompleteness($this->user, 100);

        $this->assertCount(1, $results);
        $progress = $results[0];

        // 进度为已领取（自动发放）
        $this->assertSame(TaskProgressStatus::REWARDED, $progress->status);
        $this->assertNotNull($progress->completed_at);
        $this->assertNotNull($progress->rewarded_at);
        $this->assertSame(100, $progress->current_value);

        // 用户金币增加了 50
        $this->user->refresh();
        $this->assertSame(50, $this->user->available_coins);

        // 产生了日志记录
        $this->assertDatabaseHas('task_logs', [
            'user_id' => $this->user->id,
            'task_id' => $this->completeProfileTask->id,
            'period_key' => 'all',
            'reward_type' => TaskRewardType::COIN->value,
            'reward_amount' => 50,
        ]);

        // 产生了金币流水（type=task）
        $this->assertDatabaseHas('coin_trades', [
            'user_id' => $this->user->id,
            'coins' => 50,
            'type' => 'task',
        ]);
    }

    #[Test]
    #[TestDox('任务已完成后重复触发不会重复发奖（幂等）')]
    public function update_profile_completeness_idempotent_after_completion(): void
    {
        $this->taskService->updateProfileCompleteness($this->user, 100);
        // 再次触发
        $results = $this->taskService->updateProfileCompleteness($this->user, 100);

        // 第二次调用返回空数组（已完成，跳过）
        $this->assertCount(0, $results);

        // 日志只有一条
        $this->assertDatabaseCount('task_logs', 1);
        // 金币只加了一次（50 而非 100）
        $this->user->refresh();
        $this->assertSame(50, $this->user->available_coins);
    }

    #[Test]
    #[TestDox('进度值单调递增，资料回退不会降低进度')]
    public function progress_value_monotonically_increasing(): void
    {
        // 先设置到 83%
        $this->taskService->updateProfileCompleteness($this->user, 83);
        $progress = TaskProgress::where('user_id', $this->user->id)
            ->where('task_id', $this->completeProfileTask->id)
            ->first();
        $this->assertSame(83, $progress->current_value);

        // 用户改回部分资料，完整度降到 50%，不应降低进度
        $this->taskService->updateProfileCompleteness($this->user, 50);
        $progress->refresh();
        $this->assertSame(83, $progress->current_value);
    }

    #[Test]
    #[TestDox('禁用的任务不会被处理')]
    public function disabled_task_not_processed(): void
    {
        $this->completeProfileTask->update(['status' => false]);

        $results = $this->taskService->updateProfileCompleteness($this->user, 100);
        $this->assertCount(0, $results);
        $this->assertDatabaseCount('task_progress', 0);
    }
}
