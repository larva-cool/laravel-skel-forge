<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\MonitorController;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台性能监控接口测试
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[CoversClass(MonitorController::class)]
#[Group('admin')]
#[Group('monitor')]
class MonitorControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 超级管理员
     */
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    /**
     * 以超级管理员身份发起请求
     */
    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 监控接口清单
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            '服务器资源' => ['/admin/monitor/servers', 'servers'],
            '队列吞吐' => ['/admin/monitor/queues', 'queues'],
            '缓存命中' => ['/admin/monitor/cache', 'keys'],
            '异常统计' => ['/admin/monitor/exceptions', 'exceptions'],
            '慢查询' => ['/admin/monitor/slow-queries', 'slow_queries'],
            '慢请求' => ['/admin/monitor/slow-requests', 'slow_requests'],
            '慢任务' => ['/admin/monitor/slow-jobs', 'slow_jobs'],
            '慢外部请求' => ['/admin/monitor/slow-outgoing-requests', 'slow_outgoing_requests'],
            '用户使用量' => ['/admin/monitor/usage', 'users'],
        ];
    }

    #[Test]
    #[TestDox('未登录访问监控接口返回 401')]
    #[DataProvider('endpointProvider')]
    public function guest_cannot_access_monitor_endpoints(string $uri, string $key): void
    {
        $this->getJson($uri)->assertUnauthorized();
    }

    #[Test]
    #[TestDox('超级管理员可访问监控接口并返回预期结构')]
    #[DataProvider('endpointProvider')]
    public function super_admin_can_access_monitor_endpoints(string $uri, string $key): void
    {
        $this->actingAsAdmin()
            ->getJson($uri)
            ->assertOk()
            ->assertJsonStructure(['period', $key])
            ->assertJsonPath('period', '1_hour');
    }

    #[Test]
    #[TestDox('监控接口支持切换统计周期')]
    public function monitor_endpoints_accept_period(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/monitor/exceptions?period=7_days')
            ->assertOk()
            ->assertJsonPath('period', '7_days');
    }

    #[Test]
    #[TestDox('非法统计周期返回 422')]
    public function invalid_period_is_rejected(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/monitor/servers?period=30_days')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    #[Test]
    #[TestDox('非法排序方式返回 422')]
    public function invalid_order_by_is_rejected(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/monitor/slow-queries?order_by=random')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_by');
    }

    #[Test]
    #[TestDox('慢查询支持按次数排序')]
    public function slow_queries_can_be_ordered_by_count(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/monitor/slow-queries?order_by=count')
            ->assertOk()
            ->assertJsonPath('order_by', 'count');
    }

    #[Test]
    #[TestDox('非法使用量类型返回 422')]
    public function invalid_usage_type_is_rejected(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/monitor/usage?type=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    #[Test]
    #[TestDox('使用量接口支持切换统计类型')]
    public function usage_supports_type_switching(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/monitor/usage?type=jobs')
            ->assertOk()
            ->assertJsonPath('type', 'jobs');
    }
}
