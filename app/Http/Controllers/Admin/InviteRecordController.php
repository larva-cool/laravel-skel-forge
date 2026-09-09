<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Invite\InviteRecordIndexRequest;
use App\Http\Resources\Admin\InviteRecordResource;
use App\Models\Invite\InviteRecord;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台邀请记录控制器（只读）
 *
 * 邀请关系查询，支持按邀请人、被邀请人、描述、时间范围筛选。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteRecordController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:invite-records.index')->only(['index']);
        $this->middleware('permission:invite-records.show')->only(['show']);
    }

    /**
     * 邀请记录列表（分页）
     */
    public function index(InviteRecordIndexRequest $request): AnonymousResourceCollection
    {
        $records = InviteRecord::query()
            ->with(['inviter', 'invitee'])
            ->filter($request->filters())
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return InviteRecordResource::collection($records);
    }

    /**
     * 邀请记录详情
     */
    public function show(InviteRecord $inviteRecord): InviteRecordResource
    {
        $inviteRecord->load(['inviter', 'invitee']);

        return new InviteRecordResource($inviteRecord);
    }
}
