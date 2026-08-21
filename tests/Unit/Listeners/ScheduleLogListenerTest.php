<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Enums\ScheduleStatus;
use App\Listeners\System\ScheduleLogListener;
use App\Models\System\ScheduleLog;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\TestCase;

/**
 * 调度任务执行日志监听器测试
 */
#[CoversClass(ScheduleLogListener::class)]
#[Group('listeners')]
class ScheduleLogListenerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('任务开始时写入执行中日志')]
    public function task_starting_creates_running_log(): void
    {
        $task = $this->makeTask('stats:user');

        (new ScheduleLogListener)->handle(new ScheduledTaskStarting($task));

        $this->assertDatabaseHas('schedule_logs', [
            'name' => 'stats:user',
            'type' => 'command',
            'status' => ScheduleStatus::RUNNING->value,
        ]);
    }

    #[Test]
    #[TestDox('任务完成时更新为成功状态并记录耗时')]
    public function task_finished_marks_log_success(): void
    {
        $task = $this->makeTask('telescope:prune');
        $task->exitCode = 0;
        $listener = new ScheduleLogListener;

        $listener->handle(new ScheduledTaskStarting($task));
        $listener->handle(new ScheduledTaskFinished($task, 2.5));

        $log = ScheduleLog::query()->latest('id')->first();
        $this->assertTrue($log->status->isSuccess());
        $this->assertSame(2.5, $log->runtime);
        $this->assertSame(0, $log->exit_code);
        $this->assertNotNull($log->finished_at);
    }

    #[Test]
    #[TestDox('任务失败时更新为失败状态并记录异常')]
    public function task_failed_marks_log_failed(): void
    {
        $task = $this->makeTask('model:prune');
        $task->exitCode = 1;
        $listener = new ScheduleLogListener;

        $listener->handle(new ScheduledTaskStarting($task));
        $listener->handle(new ScheduledTaskFailed($task, new RuntimeException('调度执行失败')));

        $log = ScheduleLog::query()->latest('id')->first();
        $this->assertTrue($log->status->isFailed());
        $this->assertSame(1, $log->exit_code);
        $this->assertStringContainsString('调度执行失败', $log->exception);
    }

    #[Test]
    #[TestDox('任务跳过时更新为跳过状态')]
    public function task_skipped_marks_log_skipped(): void
    {
        $task = $this->makeTask('queue:prune-batches');
        $listener = new ScheduleLogListener;

        $listener->handle(new ScheduledTaskStarting($task));
        $listener->handle(new ScheduledTaskSkipped($task));

        $log = ScheduleLog::query()->latest('id')->first();
        $this->assertTrue($log->status->isSkipped());
        $this->assertNotNull($log->finished_at);
    }

    #[Test]
    #[TestDox('开关关闭时不写入任何日志')]
    public function disabled_setting_skips_logging(): void
    {
        settings()->set('schedule.log_enabled', false);

        $task = $this->makeTask('stats:order');
        $listener = new ScheduleLogListener;

        $listener->handle(new ScheduledTaskStarting($task));
        $listener->handle(new ScheduledTaskFinished($task, 1.0));

        $this->assertDatabaseCount('schedule_logs', 0);
    }

    #[Test]
    #[TestDox('缺少执行中日志时结束事件不产生记录')]
    public function finished_without_running_log_does_nothing(): void
    {
        $task = $this->makeTask('backup:run');

        (new ScheduleLogListener)->handle(new ScheduledTaskFinished($task, 1.0));

        $this->assertDatabaseCount('schedule_logs', 0);
    }

    /**
     * 构造一个 artisan 命令调度任务
     */
    private function makeTask(string $command, string $expression = '* * * * *'): SchedulingEvent
    {
        $task = new SchedulingEvent(
            new CacheEventMutex($this->app->make(CacheFactory::class)),
            "'".PHP_BINARY."' 'artisan' {$command}"
        );
        $task->expression = $expression;

        return $task;
    }
}
