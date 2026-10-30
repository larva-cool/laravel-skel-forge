<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Enums\ScheduleTaskStatus;
use App\Models\Model;
use Database\Factories\System\ScheduleFactory;
use Illuminate\Console\Scheduling\ManagesFrequencies;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 定时任务管理模型
 *
 * @property int $id 任务ID
 * @property string $name 任务名称
 * @property string $command Artisan 命令名或 custom
 * @property string|null $command_custom 自定义 Shell 命令
 * @property array|null $params 命令参数
 * @property array|null $options 命令选项（无值）
 * @property array|null $options_with_value 带值命令选项
 * @property string $expression Cron 表达式
 * @property array|null $environments 执行环境限定
 * @property string|null $description 任务描述
 * @property bool $even_in_maintenance_mode 维护模式下也执行
 * @property bool $without_overlapping 禁止重叠执行
 * @property bool $on_one_server 仅在单台服务器上执行
 * @property bool $run_in_background 后台运行
 * @property ScheduleTaskStatus $status 状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('schedules')]
#[Fillable([
    'name', 'command', 'command_custom', 'params', 'options', 'options_with_value',
    'expression', 'environments', 'description',
    'even_in_maintenance_mode', 'without_overlapping', 'on_one_server', 'run_in_background',
    'status',
])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory, ManagesFrequencies, SoftDeletes;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'expression' => '* * * * *',
        'params' => '[]',
        'options' => '[]',
        'options_with_value' => '[]',
        'even_in_maintenance_mode' => false,
        'without_overlapping' => false,
        'on_one_server' => false,
        'run_in_background' => false,
        'status' => ScheduleTaskStatus::ACTIVE,
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
            'command' => 'string',
            'command_custom' => 'string',
            'params' => 'array',
            'options' => 'array',
            'options_with_value' => 'array',
            'expression' => 'string',
            'environments' => 'array',
            'description' => 'string',
            'even_in_maintenance_mode' => 'boolean',
            'without_overlapping' => 'boolean',
            'on_one_server' => 'boolean',
            'run_in_background' => 'boolean',
            'status' => ScheduleTaskStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 关联执行日志
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ScheduleLog::class, 'name', 'name');
    }

    /**
     * 作用域：启用的任务
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ScheduleTaskStatus::ACTIVE);
    }

    /**
     * 作用域：禁用的任务
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', ScheduleTaskStatus::INACTIVE);
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
            ->when(! empty($filters['command']), fn (Builder $q) => $q->where('command', 'like', '%'.$filters['command'].'%'))
            ->when(isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '', fn (Builder $q) => $q->where('status', (int) $filters['status']));
    }

    /**
     * 获取命令参数数组（供 Artisan::call 使用）
     *
     * @return array<int, string>
     */
    public function getArguments(): array
    {
        $params = $this->params ?? [];

        if (empty($params)) {
            return [];
        }

        // params 格式：[{"name": "arg1", "value": "val1"}, ...]
        $arguments = [];
        foreach ($params as $param) {
            if (isset($param['name'], $param['value']) && $param['value'] !== '') {
                $arguments[$param['name']] = $param['value'];
            }
        }

        return $arguments;
    }

    /**
     * 获取命令选项数组（供 Artisan::call / Schedule::command 使用）
     *
     * Laravel Schedule::compileParameters() 不会自动给选项补 -- 前缀，
     * 必须由调用方确保 key 已经是 --option 或 --option=value 的格式。
     *
     * @return array<string, string|bool>
     */
    public function getOptions(): array
    {
        $result = [];

        // 无值选项：["force", "verbose"] 或 [{"name": "force"}]
        foreach (($this->options ?? []) as $option) {
            $name = is_string($option) ? $option : ($option['name'] ?? null);
            if (! empty($name)) {
                $result[$this->normalizeOptionName($name)] = true;
            }
        }

        // 带值选项：[{"name": "hours", "value": "48"}]
        foreach (($this->options_with_value ?? []) as $option) {
            if (isset($option['name'], $option['value']) && $option['value'] !== '') {
                $result[$this->normalizeOptionName($option['name'])] = $option['value'];
            }
        }

        return $result;
    }

    /**
     * 规范化选项名，确保以 -- 开头。
     */
    protected function normalizeOptionName(string $name): string
    {
        if (str_starts_with($name, '--')) {
            return $name;
        }

        return '--'.$name;
    }

    /**
     * 获取完整的命令行字符串（用于日志展示）
     */
    public function getCommandString(): string
    {
        if ($this->command === 'custom') {
            return $this->command_custom ?? '';
        }

        $parts = ['artisan', $this->command];

        foreach ($this->getArguments() as $name => $value) {
            $parts[] = is_int($name) ? $value : "{$value}";
        }

        foreach ($this->getOptions() as $name => $value) {
            if ($value === true) {
                $parts[] = $name;
            } else {
                $parts[] = "{$name}={$value}";
            }
        }

        return implode(' ', $parts);
    }
}
