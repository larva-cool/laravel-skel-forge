<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Listeners\System;

use App\Models\System\ScheduleLog;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Throwable;

/**
 * 调度任务执行日志监听器
 *
 * 任务开始时写入一条执行中记录，结束（成功/失败/跳过）时更新该记录。
 * 日志写入失败时静默忽略，避免影响调度任务本身的执行。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleLogListener
{
    /**
     * 处理调度任务事件
     */
    public function handle(ScheduledTaskStarting|ScheduledTaskFinished|ScheduledTaskFailed|ScheduledTaskSkipped $event): void
    {
        match (true) {
            $event instanceof ScheduledTaskStarting => $this->handleTaskStarting($event),
            $event instanceof ScheduledTaskFinished => $this->handleTaskFinished($event),
            $event instanceof ScheduledTaskFailed => $this->handleTaskFailed($event),
            $event instanceof ScheduledTaskSkipped => $this->handleTaskSkipped($event),
            default => null,
        };
    }

    /**
     * 运行中的日志 ID 映射，key 为任务的 mutexName
     *
     * @var array<string, int>
     */
    protected static array $logIds = [];

    /**
     * 处理任务开始事件
     */
    protected function handleTaskStarting(ScheduledTaskStarting $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->silently(function () use ($event): void {
            $log = ScheduleLog::start($event->task);
            static::$logIds[$event->task->mutexName()] = $log->id;
        });
    }

    /**
     * 处理任务完成事件
     */
    protected function handleTaskFinished(ScheduledTaskFinished $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->silently(function () use ($event): void {
            $this->pullLog($event->task)?->markSuccess($event->runtime, $event->task->exitCode);
        });
    }

    /**
     * 处理任务失败事件
     */
    protected function handleTaskFailed(ScheduledTaskFailed $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->silently(function () use ($event): void {
            $this->pullLog($event->task)?->markFailed($event->exception, $event->task->exitCode);
        });
    }

    /**
     * 处理任务跳过事件
     */
    protected function handleTaskSkipped(ScheduledTaskSkipped $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->silently(function () use ($event): void {
            $this->pullLog($event->task)?->markSkipped();
        });
    }

    /**
     * 是否启用调度日志
     */
    protected function enabled(): bool
    {
        return (bool) settings('schedule.log_enabled', true);
    }

    /**
     * 取出并移除任务对应的执行中日志
     */
    protected function pullLog(SchedulingEvent $task): ?ScheduleLog
    {
        $mutexName = $task->mutexName();
        $logId = static::$logIds[$mutexName] ?? null;
        unset(static::$logIds[$mutexName]);

        return $logId === null ? null : ScheduleLog::query()->find($logId);
    }

    /**
     * 静默执行回调，忽略日志写入过程中的异常
     */
    protected function silently(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // 日志记录失败不应中断调度任务
        }
    }
}
