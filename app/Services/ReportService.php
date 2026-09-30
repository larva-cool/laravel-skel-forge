<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Models\Admin\Admin;
use App\Models\Content\Comment;
use App\Models\Content\Report;
use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 举报业务服务
 *
 * 负责举报提交、处理、联动处置被举报对象。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReportService
{
    /**
     * 提交举报（C 端用户）
     *
     * @param  array<string, mixed>  $data  至少包含 category / reason / evidence
     */
    public function submit(User $user, Model $target, array $data): Report
    {
        // 防止同一用户对同一对象的多次举报（待处理 / 处理中）
        $exists = Report::query()
            ->where('user_id', $user->id)
            ->where('reportable_id', $target->getKey())
            ->where('reportable_type', $target->getMorphClass())
            ->whereIn('status', [ReportStatus::PENDING->value, ReportStatus::PROCESSING->value])
            ->exists();

        if ($exists) {
            abort(422, '您已举报过该对象，请耐心等待处理');
        }

        return DB::transaction(function () use ($user, $target, $data) {
            return $user->reports()->create(array_merge([
                'reportable_id' => $target->getKey(),
                'reportable_type' => $target->getMorphClass(),
                'status' => ReportStatus::PENDING,
            ], $data));
        });
    }

    /**
     * 管理员处理举报
     *
     * @param  array{
     *     status: string,
     *     remark: ?string,
     *     actions: array<int, array{type: string, duration?: int, reason?: string}>
     * }  $payload
     */
    public function process(Report $report, Admin $admin, array $payload): Report
    {
        return DB::transaction(function () use ($report, $admin, $payload) {
            $newStatus = ReportStatus::from($payload['status']);

            $report->forceFill([
                'status' => $newStatus,
                'handler_id' => $admin->id,
                'process_remark' => $payload['remark'] ?? null,
                'process_actions' => $payload['actions'] ?? [],
                'processed_at' => Carbon::now(),
            ])->save();

            // 联动处置被举报对象（仅在「已处理」时执行；已驳回则不动作）
            if ($newStatus === ReportStatus::RESOLVED) {
                $this->applyActions($report, $payload['actions'] ?? []);
            }

            return $report->refresh()->load(['user', 'handler', 'reportable']);
        });
    }

    /**
     * 执行联动处置动作列表
     *
     * @param  array<int, array<string, mixed>>  $actions
     */
    protected function applyActions(Report $report, array $actions): void
    {
        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            match ($type) {
                'hide_comment' => $this->hideComment($report),
                'delete_comment' => $this->deleteComment($report),
                'freeze_user' => $this->freezeUser($report, (int) ($action['duration'] ?? 0)),
                default => null,
            };
        }
    }

    /**
     * 处置：隐藏被举报评论
     */
    protected function hideComment(Report $report): void
    {
        if (! $report->reportable instanceof Comment) {
            return;
        }
        $report->reportable->forceFill([
            'status' => ReviewStatus::REJECTED,
        ])->saveQuietly();
    }

    /**
     * 处置：删除被举报评论
     */
    protected function deleteComment(Report $report): void
    {
        if (! $report->reportable instanceof Comment) {
            return;
        }
        $report->reportable->delete();
    }

    /**
     * 处置：冻结被举报用户
     *
     * @param  int  $durationDays  冻结天数，0 表示永久
     */
    protected function freezeUser(Report $report, int $durationDays = 0): void
    {
        $user = null;
        if ($report->reportable instanceof User) {
            $user = $report->reportable;
        } elseif ($report->reportable instanceof Comment) {
            $user = $report->reportable->user;
        }
        if (! $user instanceof User) {
            return;
        }

        $frozenUntil = $durationDays > 0 ? Carbon::now()->addDays($durationDays) : null;
        $user->forceFill([
            'status' => UserStatus::FROZEN,
            'frozen_until' => $frozenUntil,
        ])->saveQuietly();
    }

    /**
     * 举报列表查询构造器（供 Controller 与 Export 复用）
     *
     * @param  array<string, mixed>  $filters
     */
    public function listQuery(array $filters): Builder
    {
        $query = Report::query()
            ->with(['user', 'handler', 'reportable']);

        if (! empty($filters['keyword'])) {
            $keyword = (string) $filters['keyword'];
            $query->where(function (Builder $q) use ($keyword) {
                $like = "%{$keyword}%";
                $q->where('reason', 'like', $like)
                    ->orWhere('id', (int) $keyword)
                    ->orWhereHas('user', function (Builder $uq) use ($like) {
                        $uq->where('username', 'like', $like)
                            ->orWhere('nickname', 'like', $like);
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', (string) $filters['category']);
        }

        if (! empty($filters['reportable_type'])) {
            $query->where('reportable_type', (string) $filters['reportable_type']);
        }

        if (! empty($filters['handler_id'])) {
            $query->where('handler_id', (int) $filters['handler_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', (string) $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', (string) $filters['end_date']);
        }

        return $query->orderByDesc('id');
    }

    /**
     * 举报统计快照
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        $today = Carbon::now()->toDateString();
        $pendingValues = [
            ReportStatus::PENDING->value,
            ReportStatus::PROCESSING->value,
        ];
        $closedValues = [
            ReportStatus::RESOLVED->value,
            ReportStatus::REJECTED->value,
        ];

        return [
            'total' => Report::query()->count(),
            'pending' => Report::query()->whereIn('status', $pendingValues)->count(),
            'today' => Report::query()->whereDate('created_at', $today)->count(),
            'closed' => Report::query()->whereIn('status', $closedValues)->count(),
        ];
    }
}
