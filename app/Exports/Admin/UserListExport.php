<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports\Admin;

use App\Exports\AbstractExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * 后台用户列表导出
 */
class UserListExport extends AbstractExport
{
    /**
     * 同步 / 异步导出阈值（行数）
     *
     * 超过此阈值改走队列异步生成，避免请求超时与内存溢出。
     */
    public const SYNC_THRESHOLD = 1000;

    public function __construct(private readonly array $filters = []) {}

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => 'ID',
            'username' => '用户名',
            'name' => '昵称',
            'email' => '邮箱',
            'phone' => '手机号',
            'status' => '状态',
            'available_points' => '可用积分',
            'available_coins' => '可用金币',
            'login_count' => '登录次数',
            'last_login_ip' => '最后登录IP',
            'vip_expires_at' => 'VIP过期',
            'last_login_at' => '最后登录',
            'created_at' => '注册时间',
        ];
    }

    protected function buildQuery(): Builder
    {
        $q = User::query()->orderByDesc('id');

        if ($keyword = $this->filters['keyword'] ?? null) {
            $q->where(function ($q) use ($keyword) {
                $like = "%{$keyword}%";
                $q->where('username', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if (isset($this->filters['status']) && $this->filters['status'] !== '') {
            $q->where('status', (int) $this->filters['status']);
        }

        if (($vip = $this->filters['vip'] ?? null) !== null) {
            if ($vip === '1') {
                $q->whereNotNull('vip_expires_at')->where('vip_expires_at', '>', now());
            } else {
                $q->where(function ($q) {
                    $q->whereNull('vip_expires_at')->orWhere('vip_expires_at', '<=', now());
                });
            }
        }

        if ($start = $this->filters['login_start'] ?? null) {
            $q->whereDate('last_login_at', '>=', $start);
        }
        if ($end = $this->filters['login_end'] ?? null) {
            $q->whereDate('last_login_at', '<=', $end);
        }
        if ($start = $this->filters['register_start'] ?? null) {
            $q->whereDate('created_at', '>=', $start);
        }
        if ($end = $this->filters['register_end'] ?? null) {
            $q->whereDate('created_at', '<=', $end);
        }

        return $q;
    }

    protected function sheetTitle(): string
    {
        return '用户列表';
    }

    /**
     * 状态值转中文
     */
    public function map(mixed $row): array
    {
        $result = [];
        foreach (array_keys($this->columns()) as $field) {
            $value = data_get($row, $field);
            if ($field === 'status') {
                $value = $this->statusLabel($value);
            }
            $result[] = $this->normalize($value, $field);
        }

        return $result;
    }

    private function statusLabel(mixed $status): string
    {
        if ($status instanceof \App\Enums\UserStatus) {
            return $status->label();
        }

        return match ((int) $status) {
            0 => '已冻结',
            1 => '正常',
            default => (string) $status,
        };
    }
}
