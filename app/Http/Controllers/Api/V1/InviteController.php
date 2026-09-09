<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InviteRecordResource;
use App\Models\Invite\InviteRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

/**
 * 邀请管理（前台）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteController extends Controller
{
    /**
     * InviteController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 获取我的邀请码
     */
    public function myCode(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return [
            'code' => $user->getInviteCode(),
        ];
    }

    /**
     * 我的邀请记录（分页）
     */
    public function records(Request $request): AnonymousResourceCollection
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $records = InviteRecord::where('inviter_id', $user->getKey())
            ->where('inviter_type', $user->getMorphClass())
            ->with('invitee')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return InviteRecordResource::collection($records);
    }

    /**
     * 邀请统计
     */
    public function stats(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $total = InviteRecord::where('inviter_id', $user->getKey())
            ->where('inviter_type', $user->getMorphClass())
            ->count();

        $hasInviter = InviteRecord::where('invitee_id', $user->getKey())
            ->where('invitee_type', $user->getMorphClass())
            ->exists();

        return [
            'invitee_count' => $total,
            'has_inviter' => $hasInviter,
        ];
    }
}
