<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Queue;

use App\Ai\Tools\Queue\RetryFailedJob;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Tools\Request;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 队列失败任务重试工具测试
 */
#[CoversClass(RetryFailedJob::class)]
#[Group('ai')]
#[Group('queue')]
class RetryFailedJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建一个仅拥有指定权限的管理员
     *
     * @param  array<int, string>  $permissions
     */
    private function createAdminWithPermissions(array $permissions): Admin
    {
        $suffix = uniqid();

        $admin = Admin::query()->create([
            'username' => 'limited_'.$suffix,
            'name' => '受限管理员',
            'email' => "limited_{$suffix}@example.com",
            'password' => 'password',
            'status' => AdminStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'limited_'.$suffix,
            'display_name' => '受限角色',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        return $admin->refresh();
    }

    /**
     * 模拟 Horizon 任务仓储返回指定任务
     */
    private function fakeJobRepository(?object $job): void
    {
        $repository = $this->mock(JobRepository::class);
        $repository->shouldReceive('getJobs')->andReturn(collect($job ? [$job] : []));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new RetryFailedJob)->handle(new Request(['id' => '1']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 horizon.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        Bus::fake();

        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new RetryFailedJob)->forAdmin($admin)->handle(new Request(['id' => '1']));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        Bus::assertNotDispatched(HorizonRetryFailedJob::class);
    }

    #[Test]
    #[TestDox('重试存在的失败任务时派发 Horizon 重试任务')]
    public function handle_dispatches_retry_job(): void
    {
        Bus::fake();

        $admin = $this->createAdminWithPermissions(['horizon.index']);
        $this->fakeJobRepository((object) [
            'id' => '42',
            'name' => 'App\\Jobs\\SyncSegmentJob',
            'queue' => 'segments',
            'status' => 'failed',
        ]);

        $result = (new RetryFailedJob)->forAdmin($admin)->handle(new Request(['id' => '42']));

        $this->assertStringContainsString('重新推入队列', (string) $result);
        Bus::assertDispatched(HorizonRetryFailedJob::class, fn (HorizonRetryFailedJob $job) => true);
    }

    #[Test]
    #[TestDox('任务不存在或已被处理时操作被取消')]
    public function handle_cancels_when_job_not_found(): void
    {
        Bus::fake();

        $admin = $this->createAdminWithPermissions(['horizon.index']);
        $this->fakeJobRepository(null);

        $result = (new RetryFailedJob)->forAdmin($admin)->handle(new Request(['id' => '999']));

        $this->assertStringContainsString('未找到', (string) $result);
        Bus::assertNotDispatched(HorizonRetryFailedJob::class);
    }

    #[Test]
    #[TestDox('非失败状态的任务不允许重试')]
    public function handle_cancels_when_job_not_failed(): void
    {
        Bus::fake();

        $admin = $this->createAdminWithPermissions(['horizon.index']);
        $this->fakeJobRepository((object) [
            'id' => '42',
            'name' => 'App\\Jobs\\SyncSegmentJob',
            'queue' => 'segments',
            'status' => 'completed',
        ]);

        $result = (new RetryFailedJob)->forAdmin($admin)->handle(new Request(['id' => '42']));

        $this->assertStringContainsString('未找到', (string) $result);
        Bus::assertNotDispatched(HorizonRetryFailedJob::class);
    }
}
