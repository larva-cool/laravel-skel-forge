<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Models\Admin\Admin;
use App\Models\System\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 操作审计日志模型单元测试
 */
#[CoversClass(AuditLog::class)]
#[Group('audit-log')]
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('payload 以数组形式读写')]
    public function payload_is_cast_to_array(): void
    {
        $log = AuditLog::factory()->create(['payload' => ['name' => '张三', 'age' => 18]]);

        $this->assertSame(['name' => '张三', 'age' => 18], $log->fresh()->payload);
    }

    #[Test]
    #[TestDox('模型不维护 updated_at 字段')]
    public function model_has_no_updated_at(): void
    {
        $this->assertNull(AuditLog::UPDATED_AT);
    }

    #[Test]
    #[TestDox('operator 关联到管理员')]
    public function operator_relation_returns_admin(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();
        $log = AuditLog::factory()->create(['operator_id' => $admin->id]);

        $this->assertInstanceOf(Admin::class, $log->operator);
        $this->assertSame($admin->id, $log->operator->id);
    }

    #[Test]
    #[TestDox('操作人为空时 operator 返回 null')]
    public function operator_relation_returns_null_without_operator(): void
    {
        $log = AuditLog::factory()->create(['operator_id' => null]);

        $this->assertNull($log->operator);
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function statusCodeProvider(): array
    {
        return [
            '200 成功' => [200, false],
            '302 重定向' => [302, false],
            '399 边界' => [399, false],
            '400 请求错误' => [400, true],
            '422 校验失败' => [422, true],
            '500 服务端错误' => [500, true],
        ];
    }

    #[Test]
    #[DataProvider('statusCodeProvider')]
    #[TestDox('按状态码判定是否为失败操作')]
    public function is_failed_reflects_status_code(int $statusCode, bool $expected): void
    {
        $log = AuditLog::factory()->create(['status_code' => $statusCode]);

        $this->assertSame($expected, $log->isFailed());
    }

    #[Test]
    #[TestDox('超长错误消息被截断')]
    public function format_error_truncates_long_message(): void
    {
        $error = AuditLog::formatError(str_repeat('a', AuditLog::ERROR_MAX_LENGTH + 500));

        $this->assertSame(AuditLog::ERROR_MAX_LENGTH + 3, strlen($error));
        $this->assertStringEndsWith('...', $error);
    }

    #[Test]
    #[TestDox('短错误消息保持原样')]
    public function format_error_keeps_short_message(): void
    {
        $this->assertSame('给定的数据无效。', AuditLog::formatError('给定的数据无效。'));
    }

    #[Test]
    #[TestDox('筛选作用域按操作人名称模糊匹配')]
    public function filter_scope_matches_operator_name(): void
    {
        AuditLog::factory()->create(['operator_name' => '张三']);
        AuditLog::factory()->create(['operator_name' => '李四']);

        $this->assertSame(1, AuditLog::query()->filter(['operator_name' => '张'])->count());
    }

    #[Test]
    #[TestDox('筛选作用域按状态码精确匹配')]
    public function filter_scope_matches_status_code(): void
    {
        AuditLog::factory()->create(['status_code' => 200]);
        AuditLog::factory()->failed()->create();

        $this->assertSame(1, AuditLog::query()->filter(['status_code' => 422])->count());
    }

    #[Test]
    #[TestDox('筛选作用域可只返回成功操作')]
    public function filter_scope_returns_successful_logs(): void
    {
        AuditLog::factory()->create(['status_code' => 200]);
        AuditLog::factory()->failed()->create();

        $this->assertSame(1, AuditLog::query()->filter(['is_failed' => false])->count());
    }

    #[Test]
    #[TestDox('筛选作用域按时间区间过滤')]
    public function filter_scope_matches_date_range(): void
    {
        AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(10)]);
        AuditLog::factory()->create(['created_at' => Carbon::now()]);

        $count = AuditLog::query()->filter([
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'end_date' => Carbon::now()->toDateString(),
        ])->count();

        $this->assertSame(1, $count);
    }

    #[Test]
    #[TestDox('空筛选条件返回全部记录')]
    public function filter_scope_without_conditions_returns_all(): void
    {
        AuditLog::factory()->count(3)->create();

        $this->assertSame(3, AuditLog::query()->filter([])->count());
    }

    #[Test]
    #[TestDox('可修剪查询按保留天数筛选过期记录')]
    public function prunable_query_matches_expired_logs(): void
    {
        settings()->set('audit.log_prunable_days', 30);

        $expired = AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(60)]);
        $kept = AuditLog::factory()->create(['created_at' => Carbon::now()]);

        $ids = (new AuditLog)->prunable()->pluck('id')->all();

        $this->assertContains($expired->id, $ids);
        $this->assertNotContains($kept->id, $ids);
    }
}
