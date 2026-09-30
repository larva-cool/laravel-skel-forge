<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin\Admin;
use App\Models\Content\Report;
use App\Models\User;

/**
 * 举报策略
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReportPolicy
{
    /**
     * 任意用户/管理员均可查看列表（具体权限由中间件 + 后台 Guard 控制）
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * C 端用户查看自己的举报详情
     */
    public function view(User $user, Report $report): bool
    {
        return $user->id === $report->user_id;
    }

    /**
     * C 端用户提交举报（须登录且目标已存在）
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * 后台管理员处理举报
     */
    public function process(Admin $admin, Report $report): bool
    {
        return true;
    }

    /**
     * 后台管理员导出举报数据
     */
    public function export(Admin $admin): bool
    {
        return true;
    }
}
