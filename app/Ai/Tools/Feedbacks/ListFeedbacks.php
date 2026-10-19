<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Feedbacks;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use App\Models\System\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 用户反馈查询工具。
 *
 * 支持按关键字、类型、状态、优先级、提交用户、提交时间范围筛选，
 * 返回分页反馈数据。只读操作。
 */
class ListFeedbacks implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'feedbacks.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询用户意见反馈列表。支持按关键字（编号/标题/描述/联系方式）、反馈类型、处理状态、优先级、提交用户 ID、提交时间范围筛选，以分页形式返回反馈信息（含处理状态、优先级、处理备注等）。只读操作，不能执行处理或回复。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配关键字，作用于反馈编号、标题、描述、联系人、联系方式'),
            'type' => $schema->string()
                ->max(50)
                ->description('反馈类型筛选（字典 feedback_type 的 value，如 bug、suggestion 等）'),
            'status' => $schema->integer()
                ->enum([0, 1, 2, 3, 4, 5])
                ->description('处理状态筛选：0=待处理，1=分析中，2=已规划，3=处理中，4=已解决，5=已关闭'),
            'priority' => $schema->integer()
                ->enum([1, 2, 3, 4])
                ->description('优先级筛选：1=低，2=中，3=高，4=紧急'),
            'user_id' => $schema->integer()
                ->description('精确按提交用户 ID 筛选'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('提交起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('提交截止日期（YYYY-MM-DD），含当天'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $query = Feedback::query();

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($q) use ($keyword) {
                $like = "%{$keyword}%";
                $q->where('code', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('content', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('contact_info', 'like', $like);
            });
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($request->has('status')) {
            $query->where('status', FeedbackStatus::from($request->integer('status')));
        }

        if ($request->has('priority')) {
            $query->where('priority', FeedbackPriority::from($request->integer('priority')));
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($startDate = $request->string('start_date')->toString()) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->string('end_date')->toString()) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Feedback $feedback) => [
            'id' => $feedback->id,
            'code' => $feedback->code,
            'title' => $feedback->title,
            'content' => $feedback->content,
            'images' => $feedback->images,
            'type' => $feedback->type,
            'status' => $feedback->status->value,
            'status_label' => $feedback->status->label(),
            'priority' => $feedback->priority->value,
            'priority_label' => $feedback->priority->label(),
            'contact_name' => $feedback->contact_name,
            'contact_info' => $feedback->contact_info,
            'user_id' => $feedback->user_id,
            'admin_remark' => $feedback->admin_remark,
            'resolved_at' => $feedback->resolved_at?->toDateTimeString(),
            'closed_at' => $feedback->closed_at?->toDateTimeString(),
            'created_at' => $feedback->created_at?->toDateTimeString(),
        ]);

        return json_encode([
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
