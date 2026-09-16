<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\Identity\SubmitIdentityRequest;
use App\Http\Resources\Api\V1\IdentityVerificationResource;
use App\Models\User\IdentityVerification;
use App\Services\IdentityVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 实名认证接口（用户端）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdentityController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 当前用户最新一条认证记录
     */
    public function show(Request $request): IdentityVerificationResource
    {
        $user = $request->user();
        $verification = IdentityVerification::query()
            ->where('verifiable_type', $user::class)
            ->where('verifiable_id', $user->id)
            ->orderByDesc('id')
            ->first();

        return new IdentityVerificationResource($verification);
    }

    /**
     * 提交实名认证
     */
    public function submit(SubmitIdentityRequest $request, IdentityVerificationService $service): IdentityVerificationResource
    {
        $verification = $service->submit($request->user(), $request->validated());

        return new IdentityVerificationResource($verification);
    }

    /**
     * 当前认证状态简要信息
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $user = $user->fresh();
        $latest = IdentityVerification::query()
            ->where('verifiable_type', $user::class)
            ->where('verifiable_id', $user->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'is_verified' => $user->isVerified(),
            'identity_type' => $user->identity_type?->value,
            'identity_type_label' => $user->identity_type?->label(),
            'latest_status' => $latest?->status?->value,
            'latest_status_label' => $latest?->status?->label(),
            'latest_reject_reason' => $latest && $latest->status === ReviewStatus::REJECTED ? $latest->reject_reason : null,
            'latest_reject_remark' => $latest && $latest->status === ReviewStatus::REJECTED ? $latest->reject_remark : null,
        ]);
    }
}
