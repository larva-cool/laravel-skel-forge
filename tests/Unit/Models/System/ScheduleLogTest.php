<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Enums\ScheduleStatus;
use App\Models\System\ScheduleLog;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\TestCase;

/**
 * ScheduleLog 模型单元测试
 */
#[CoversClass(ScheduleLog::class)]
#[Group('models')]
class ScheduleLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('CREATED_AT 映射为 started_at 且 UPDATED_AT 为 null')]
    public function timestamp_constants_are_customized(): void
    {
        $this->assertSame('started_at', ScheduleLog::CREATED_AT);
        $this->assertNull(ScheduleLog::UPDATED_AT);
    }

    #[Test]
    #[TestDox('工厂创建日志并正确转换状态枚举')]
    public function factory_creates_log_with_status_enum(): void
    {
        $log = ScheduleLog::factory()->create();

        $this->assertInstanceOf(ScheduleStatus::class, $log->status);
        $this->assertTrue($log->status->isSuccess());
    }

    #[Test]
    #[TestDox('start 为调度任务创建执行中记录')]
    public function start_creates_running_log(): void
    {
        $log = ScheduleLog::start($this->makeTask('stats:user', '0 1 * * *'));

        $this->assertTrue($log->status->isRunning());
        $this->assertSame('stats:user', $log->name);
        $this->assertSame('command', $log->type);
        $this->assertSame('0 1 * * *', $log->expression);
        $this->assertNotNull($log->started_at);
        $this->assertNull($log->finished_at);
    }

    #[Test]
    #[TestDox('markSuccess 更新状态、耗时与退出码')]
    public function mark_success_updates_log(): void
    {
        $log = ScheduleLog::factory()->running()->create();

        $log->markSuccess(1.23456, 0);

        $log->refresh();
        $this->assertTrue($log->status->isSuccess());
        $this->assertSame(1.235, $log->runtime);
        $this->assertSame(0, $log->exit_code);
        $this->assertNotNull($log->finished_at);
    }

    #[Test]
    #[TestDox('markFailed 写入异常摘要')]
    public function mark_failed_records_exception(): void
    {
        $log = ScheduleLog::factory()->running()->create();

        $log->markFailed(new RuntimeException('任务执行异常'), 1);

        $log->refresh();
        $this->assertTrue($log->status->isFailed());
        $this->assertSame(1, $log->exit_code);
        $this->assertStringContainsString(RuntimeException::class, $log->exception);
        $this->assertStringContainsString('任务执行异常', $log->exception);
    }

    #[Test]
    #[TestDox('markSkipped 更新为跳过状态')]
    public function mark_skipped_updates_log(): void
    {
        $log = ScheduleLog::factory()->running()->create();

        $log->markSkipped();

        $log->refresh();
        $this->assertTrue($log->status->isSkipped());
        $this->assertNotNull($log->finished_at);
    }

    #[Test]
    #[TestDox('resolveName 剥离 php artisan 前缀')]
    public function resolve_name_strips_artisan_prefix(): void
    {
        $task = $this->makeTask('telescope:prune --hours=48');

        $this->assertSame('telescope:prune --hours=48', ScheduleLog::resolveName($task));
    }

    #[Test]
    #[TestDox('resolveName 对闭包任务回退为描述')]
    public function resolve_name_falls_back_to_description_for_callback(): void
    {
        $task = new CallbackEvent($this->eventMutex(), fn () => null);
        $task->description('自定义闭包任务');

        $this->assertSame('自定义闭包任务', ScheduleLog::resolveName($task));
        $this->assertSame('callback', ScheduleLog::resolveType($task));
    }

    #[Test]
    #[TestDox('resolveType 对非 artisan 命令返回 exec')]
    public function resolve_type_returns_exec_for_shell_command(): void
    {
        $task = new SchedulingEvent($this->eventMutex(), 'ls -al');

        $this->assertSame('exec', ScheduleLog::resolveType($task));
    }

    #[Test]
    #[TestDox('formatException 截断超长内容')]
    public function format_exception_is_truncated(): void
    {
        $exception = new RuntimeException(str_repeat('长', 10000));

        $content = ScheduleLog::formatException($exception);

        $this->assertLessThanOrEqual(ScheduleLog::EXCEPTION_MAX_LENGTH, mb_strlen($content));
    }

    #[Test]
    #[TestDox('prunable 按保留天数筛选过期日志')]
    public function prunable_selects_expired_logs(): void
    {
        settings()->set('schedule.log_prunable_days', 7);

        $expired = ScheduleLog::factory()->create(['started_at' => Carbon::now()->subDays(8)]);
        $fresh = ScheduleLog::factory()->create(['started_at' => Carbon::now()->subDay()]);

        $ids = (new ScheduleLog)->prunable()->pluck('id')->all();

        $this->assertContains($expired->id, $ids);
        $this->assertNotContains($fresh->id, $ids);
    }

    #[Test]
    #[TestDox('scopeFilter 支持按名称、状态与时间筛选')]
    public function scope_filter_applies_conditions(): void
    {
        ScheduleLog::factory()->create(['name' => 'stats:user', 'started_at' => Carbon::now()->subDays(3)]);
        $target = ScheduleLog::factory()->failed()->create(['name' => 'model:prune', 'started_at' => Carbon::now()]);

        $results = ScheduleLog::query()->filter([
            'name' => 'prune',
            'status' => ScheduleStatus::FAILED->value,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'end_date' => Carbon::now()->toDateString(),
        ])->get();

        $this->assertCount(1, $results);
        $this->assertSame($target->id, $results->first()->id);
    }

    /**
     * 构造一个 artisan 命令调度任务
     */
    private function makeTask(string $command, string $expression = '* * * * *'): SchedulingEvent
    {
        $task = new SchedulingEvent($this->eventMutex(), "'".PHP_BINARY."' 'artisan' {$command}");
        $task->expression = $expression;

        return $task;
    }

    /**
     * 获取事件互斥实现
     */
    private function eventMutex(): CacheEventMutex
    {
        return new CacheEventMutex($this->app->make(CacheFactory::class));
    }
}
