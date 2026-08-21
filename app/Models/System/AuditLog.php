<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Models\Admin\Admin;
use App\Models\Model;
use Database\Factories\System\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 操作审计日志模型
 *
 * @property int $id 日志ID
 * @property int|null $operator_id 操作人ID
 * @property string|null $operator_name 操作人名称快照
 * @property string|null $route 路由名称
 * @property string $method 请求方法
 * @property string $uri 请求路径
 * @property string|null $title 操作描述
 * @property array<string, mixed>|null $payload 请求参数（已脱敏）
 * @property int $status_code 响应状态码
 * @property string|null $error 失败时的响应消息
 * @property float|null $runtime 执行耗时（秒）
 * @property string|null $ip 请求IP
 * @property string|null $user_agent 用户代理
 * @property Carbon $created_at 操作时间
 * @property-read Admin|null $operator 操作人
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('audit_logs')]
#[Fillable([
    'operator_id', 'operator_name', 'route', 'method', 'uri', 'title',
    'payload', 'status_code', 'error', 'runtime', 'ip', 'user_agent',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, MassPrunable;

    /** @var int 失败响应消息最大保留长度 */
    public const int ERROR_MAX_LENGTH = 2000;

    /** @var int 用户代理最大保留长度 */
    public const int USER_AGENT_MAX_LENGTH = 512;

    // 时间定义
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'operator_id' => 'integer',
            'operator_name' => 'string',
            'route' => 'string',
            'method' => 'string',
            'uri' => 'string',
            'title' => 'string',
            'payload' => 'array',
            'status_code' => 'integer',
            'error' => 'string',
            'runtime' => 'float',
            'ip' => 'string',
            'user_agent' => 'string',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 操作人
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'operator_id');
    }

    /**
     * 获取可修剪模型查询构造器。
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', Carbon::now()->subDays((int) settings('audit.log_prunable_days', 180)));
    }

    /**
     * 作用域：列表筛选
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['operator_id']), fn (Builder $q) => $q->where('operator_id', (int) $filters['operator_id']))
            ->when(! empty($filters['operator_name']), fn (Builder $q) => $q->where('operator_name', 'like', '%'.$filters['operator_name'].'%'))
            ->when(! empty($filters['route']), fn (Builder $q) => $q->where('route', 'like', '%'.$filters['route'].'%'))
            ->when(! empty($filters['method']), fn (Builder $q) => $q->where('method', strtoupper((string) $filters['method'])))
            ->when(! empty($filters['uri']), fn (Builder $q) => $q->where('uri', 'like', '%'.$filters['uri'].'%'))
            ->when(! empty($filters['status_code']), fn (Builder $q) => $q->where('status_code', (int) $filters['status_code']))
            ->when(isset($filters['is_failed']) && $filters['is_failed'] !== null && $filters['is_failed'] !== '', function (Builder $q) use ($filters) {
                return filter_var($filters['is_failed'], FILTER_VALIDATE_BOOLEAN)
                    ? $q->where('status_code', '>=', 400)
                    : $q->where('status_code', '<', 400);
            })
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay()))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay()));
    }

    /**
     * 是否为失败操作
     */
    public function isFailed(): bool
    {
        return $this->status_code >= 400;
    }

    /**
     * 截断失败响应消息
     */
    public static function formatError(string $error): string
    {
        return Str::limit($error, self::ERROR_MAX_LENGTH, '...');
    }
}
