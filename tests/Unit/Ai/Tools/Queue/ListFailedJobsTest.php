<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Queue;

use App\Ai\Tools\Queue\ListFailedJobs;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Laravel\Horizon\Contracts\JobRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 队列失败任务查询工具测试
 */
#[CoversClass(ListFailedJobs::class)]
#[Group('ai')]
#[Group('queue')]
class ListFailedJobsTest extends TestCase
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
     * 模拟 Horizon 失败任务仓储
     *
     * @param  array<int, object>  $jobs
     */
    private function fakeJobRepository(array $jobs, int $total): void
    {
        $repository = $this->mock(JobRepository::class);
        $repository->shouldReceive('getFailed')->andReturn(collect($jobs));
        $repository->shouldReceive('countFailed')->andReturn($total);
    }

    /**
     * 构造一个失败任务对象
     */
    private function fakeJob(string $id, string $name, string $queue = 'default', string $exception = 'RuntimeException: boom'): object
    {
        return (object) [
            'id' => $id,
            'name' => $name,
            'queue' => $queue,
            'status' => 'failed',
            'failed_at' => '2026-10-19 10:00:00',
            'exception' => $exception,
        ];
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListFailedJobs)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 horizon.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListFailedJobs)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回失败任务列表')]
    public function handle_returns_failed_jobs(): void
    {
        $admin = $this->createAdminWithPermissions(['horizon.index']);
        $this->fakeJobRepository([
            $this->fakeJob('1', 'App\\Jobs\\SyncSegmentJob', 'segments'),
            $this->fakeJob('2', 'App\\Jobs\\SendMailJob'),
        ], 5);

        $result = (new ListFailedJobs)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(5, $payload['total']);
        $this->assertSame(2, $payload['returned']);
        $this->assertSame('App\\Jobs\\SyncSegmentJob', $payload['data'][0]['name']);
        $this->assertSame('segments', $payload['data'][0]['queue']);
    }

    #[Test]
    #[TestDox('keyword 过滤匹配任务类名或队列名')]
    public function handle_filters_by_keyword(): void
    {
        $admin = $this->createAdminWithPermissions(['horizon.index']);
        $this->fakeJobRepository([
            $this->fakeJob('1', 'App\\Jobs\\SyncSegmentJob', 'segments'),
            $this->fakeJob('2', 'App\\Jobs\\SendMailJob'),
        ], 2);

        $result = (new ListFailedJobs)->forAdmin($admin)->handle(new Request(['keyword' => 'Segment']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['returned']);
        $this->assertSame('App\\Jobs\\SyncSegmentJob', $payload['data'][0]['name']);
    }

    #[Test]
    #[TestDox('异常信息按 500 字符截断返回')]
    public function handle_truncates_exception(): void
    {
        $admin = $this->createAdminWithPermissions(['horizon.index']);
        $this->fakeJobRepository([
            $this->fakeJob('1', 'App\\Jobs\\SomeJob', 'default', str_repeat('x', 800)),
        ], 1);

        $result = (new ListFailedJobs)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(500, mb_strlen($payload['data'][0]['exception']));
    }
}
