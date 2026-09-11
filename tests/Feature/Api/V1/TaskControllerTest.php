<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\Gender;
use App\Http\Controllers\Api\V1\TaskController;
use App\Models\Task\Task;
use App\Models\Task\TaskGroup;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 任务中心 API 功能测试
 */
#[CoversClass(TaskController::class)]
#[Group('api')]
#[Group('task')]
class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Task $completeProfileTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => null,
            'avatar' => null,
        ]);

        $newbieGroup = TaskGroup::where('code', 'newbie')->firstOrFail();
        $this->completeProfileTask = Task::where('code', 'complete_profile')
            ->where('group_id', $newbieGroup->id)
            ->firstOrFail();
    }

    #[Test]
    #[TestDox('未登录用户访问任务中心返回 401')]
    public function guest_cannot_access_task_list(): void
    {
        $response = $this->getJson('/api/v1/tasks');

        $response->assertUnauthorized();
    }

    #[Test]
    #[TestDox('登录用户可获取任务中心列表（含分组和进度）')]
    public function authenticated_user_can_get_task_list(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tasks');

        $response->assertOk();
        $data = $response->json();
        $newbieGroup = collect($data)->firstWhere('code', 'newbie');
        $this->assertNotNull($newbieGroup);
        $this->assertArrayHasKey('tasks', $newbieGroup);
        $completeTask = collect($newbieGroup['tasks'])->firstWhere('code', 'complete_profile');
        $this->assertNotNull($completeTask);
        $this->assertSame(0, $completeTask['progress']['current_value']);
        $this->assertFalse($completeTask['progress']['is_completed']);
        $this->assertArrayHasKey('reward_type', $completeTask);
        $this->assertArrayHasKey('target_value', $completeTask);
        $this->assertArrayHasKey('can_claim', $completeTask['progress']);
    }

    #[Test]
    #[TestDox('任务完成后自动发放奖励的任务无需领取，直接在列表中显示已领取')]
    public function auto_reward_task_shows_rewarded_after_completion(): void
    {
        Sanctum::actingAs($this->user);

        // 填满资料触发自动完成和发奖
        $this->user->update(['name' => '测试昵称', 'avatar' => 'avatar.jpg']);
        $this->user->profile->update([
            'gender' => Gender::GENDER_FEMALE,
            'birthday' => '2000-01-01',
            'bio' => '个性签名',
            'province_id' => 1,
            'city_id' => 1,
            'district_id' => 1,
        ]);
        // 直接调用服务模拟资料更新后触发
        $taskService = new TaskService;
        $taskService->updateProfileCompleteness($this->user->fresh()->loadMissing('profile'), 100);

        $response = $this->getJson('/api/v1/tasks');
        $response->assertOk();

        $data = $response->json();
        $newbieGroup = collect($data)->firstWhere('code', 'newbie');
        $completeTask = collect($newbieGroup['tasks'])->firstWhere('code', 'complete_profile');
        $this->assertNotNull($completeTask);

        // 已领取奖励
        $this->assertTrue($completeTask['progress']['is_rewarded']);
        $this->assertSame(100, $completeTask['progress']['current_value']);
        $this->assertSame(50, $this->user->fresh()->available_coins);
    }

    #[Test]
    #[TestDox('手动领取未完成的任务返回 422')]
    public function claim_incomplete_task_returns_validation_error(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/tasks/{$this->completeProfileTask->id}/claim");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['task_id']);
    }

    #[Test]
    #[TestDox('领取不存在的任务返回 422')]
    public function claim_nonexistent_task_returns_validation_error(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/tasks/99999/claim');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['task_id']);
    }
}
