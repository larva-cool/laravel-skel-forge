<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Enums\ScheduleStatus;
use App\Models\Model;
use Database\Factories\System\ScheduleLogFactory;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * 调度任务执行日志模型
 *
 * @property int $id 日志ID
 * @property string $name 任务名称
 * @property string $type 任务类型
 * @property string $expression Cron 表达式
 * @property ScheduleStatus $status 执行状态
 * @property int|null $exit_code 退出码
 * @property float|null $runtime 执行耗时（秒）
 * @property string|null $exception 异常信息
 * @property string|null $hostname 执行主机名
 * @property Carbon $started_at 开始时间
 * @property Carbon|null $finished_at 结束时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('schedule_logs')]
#[Fillable(['name', 'type', 'expression', 'status', 'exit_code', 'runtime', 'exception', 'hostname', 'started_at', 'finished_at'])]
class ScheduleLog extends Model
{
    /** @use HasFactory<ScheduleLogFactory> */
    use HasFactory, MassPrunable;

    /** @var int 异常信息最大保留长度 */
    public const int EXCEPTION_MAX_LENGTH = 5000;

    // 时间定义
    public const CREATED_AT = 'started_at';
    public const UPDATED_AT = null;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'type' => 'command',
        'expression' => '',
        'status' => ScheduleStatus::RUNNING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'type' => 'string',
            'expression' => 'string',
            'status' => ScheduleStatus::class,
            'exit_code' => 'integer',
            'runtime' => 'float',
            'exception' => 'string',
            'hostname' => 'string',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * 获取可修剪模型查询构造器。
     */
    public function prunable(): Builder
    {
        return static::query()->where('started_at', '<=', Carbon::now()->subDays((int) settings('schedule.log_prunable_days', 30)));
    }

    /**
     * 作用域：列表筛选
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['name']), fn (Builder $q) => $q->where('name', 'like', '%'.$filters['name'].'%'))
            ->when(! empty($filters['type']), fn (Builder $q) => $q->where('type', $filters['type']))
            ->when(isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '', fn (Builder $q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->where('started_at', '>=', Carbon::parse($filters['start_date'])->startOfDay()))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->where('started_at', '<=', Carbon::parse($filters['end_date'])->endOfDay()));
    }

    /**
     * 为调度任务创建执行中的日志记录
     */
    public static function start(SchedulingEvent $task): static
    {
        return static::create([
            'name' => static::resolveName($task),
            'type' => static::resolveType($task),
            'expression' => $task->getExpression(),
            'status' => ScheduleStatus::RUNNING,
            'hostname' => Str::limit(gethostname() ?: '', 64, ''),
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * 标记任务执行成功
     */
    public function markSuccess(float $runtime, ?int $exitCode = null): bool
    {
        return $this->update([
            'status' => ScheduleStatus::SUCCESS,
            'runtime' => round($runtime, 3),
            'exit_code' => $exitCode,
            'finished_at' => Carbon::now(),
        ]);
    }

    /**
     * 标记任务执行失败
     */
    public function markFailed(Throwable $exception, ?int $exitCode = null): bool
    {
        return $this->update([
            'status' => ScheduleStatus::FAILED,
            'exit_code' => $exitCode,
            'exception' => static::formatException($exception),
            'finished_at' => Carbon::now(),
        ]);
    }

    /**
     * 标记任务被跳过
     */
    public function markSkipped(): bool
    {
        return $this->update([
            'status' => ScheduleStatus::SKIPPED,
            'finished_at' => Carbon::now(),
        ]);
    }

    /**
     * 解析任务名称
     */
    public static function resolveName(SchedulingEvent $task): string
    {
        $name = $task->command
            ? trim(preg_replace('/^.*artisan["\']?\s*/', '', SchedulingEvent::normalizeCommand($task->command)) ?? '')
            : '';

        if ($name === '') {
            $name = (string) ($task->description ?: $task->getSummaryForDisplay());
        }

        return Str::limit($name, 255, '');
    }

    /**
     * 解析任务类型
     */
    public static function resolveType(SchedulingEvent $task): string
    {
        if ($task instanceof CallbackEvent) {
            return 'callback';
        }

        return str_contains((string) $task->command, 'artisan') ? 'command' : 'exec';
    }

    /**
     * 格式化异常信息（类名 + 消息 + 位置 + 截断的堆栈）
     */
    public static function formatException(Throwable $exception): string
    {
        $content = sprintf(
            "%s: %s\nat %s:%d\n%s",
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        return Str::limit($content, self::EXCEPTION_MAX_LENGTH, '...');
    }
}
