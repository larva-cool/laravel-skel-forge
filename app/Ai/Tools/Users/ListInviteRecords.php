<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Invite\InviteRecord;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 邀请记录查询工具。
 *
 * 支持按邀请人、被邀请人、邀请时间范围筛选，返回分页邀请关系数据。只读操作。
 */
class ListInviteRecords implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'invite-records.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询用户邀请关系记录。支持按邀请人用户 ID、被邀请人用户 ID、邀请时间范围筛选，以分页形式返回邀请记录（含邀请人/被邀请人概要信息）。用于核查邀请链路、排查邀请奖励纠纷。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'inviter_id' => $schema->integer()
                ->description('精确按邀请人用户 ID 筛选，查询某用户邀请了下线'),
            'invitee_id' => $schema->integer()
                ->description('精确按被邀请人用户 ID 筛选，查询某用户的上线邀请人'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('邀请起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('邀请截止日期（YYYY-MM-DD），含当天'),
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

        $filters = [];

        if ($inviterId = $request->integer('inviter_id')) {
            $filters['inviter_id'] = $inviterId;
        }
        if ($inviteeId = $request->integer('invitee_id')) {
            $filters['invitee_id'] = $inviteeId;
        }
        if ($startDate = $request->string('start_date')->toString()) {
            $filters['created_start'] = $startDate;
        }
        if ($endDate = $request->string('end_date')->toString()) {
            $filters['created_end'] = $endDate;
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = InviteRecord::query()
            ->filter($filters)
            ->with(['inviter', 'invitee'])
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (InviteRecord $record) => [
            'id' => $record->id,
            'inviter' => $record->inviter instanceof User ? [
                'id' => $record->inviter->id,
                'username' => $record->inviter->username,
                'name' => $record->inviter->name,
            ] : ['id' => $record->inviter_id],
            'invitee' => $record->invitee instanceof User ? [
                'id' => $record->invitee->id,
                'username' => $record->invitee->username,
                'name' => $record->invitee->name,
            ] : ['id' => $record->invitee_id],
            'description' => $record->description,
            'created_at' => $record->created_at?->toDateTimeString(),
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
