<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Identity\IdentityIndexRequest;
use App\Http\Requests\Admin\Identity\RejectIdentityRequest;
use App\Http\Resources\Admin\IdentityVerificationResource;
use App\Http\Resources\Admin\IdentityVerificationStatResource;
use App\Models\User;
use App\Models\User\IdentityVerification;
use App\Services\IdentityVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台实名认证管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdentityVerificationController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:identity-verifications.index')->only(['index', 'show', 'stats']);
        $this->middleware('permission:identity-verifications.edit')->only(['approve', 'reject']);
    }

    /**
     * 认证记录列表（分页）
     */
    public function index(IdentityIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->filters();
        $perPage = per_page($request);

        $query = IdentityVerification::query()->with(['verifiable', 'admin', 'idCardFront', 'idCardBack', 'handheld', 'businessLicense', 'openingPermit']);

        if ($filters['verifiable_id'] ?? null) {
            $query->where('verifiable_id', $filters['verifiable_id']);
        }

        if ($keyword = $filters['keyword'] ?? null) {
            $query->where(function ($q) use ($keyword) {
                $like = "%{$keyword}%";
                $q->where('real_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('credit_code', 'like', $like)
                    ->orWhere('legal_person_name', 'like', $like)
                    ->orWhereHasMorph('verifiable', [User::class], function ($uq) use ($like) {
                        $uq->where('name', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
            });
        }

        if ($filters['type'] ?? null) {
            $query->where('type', $filters['type']);
        }

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['start_date'] ?? null) {
            $query->whereDate('submitted_at', '>=', $filters['start_date']);
        }

        if ($filters['end_date'] ?? null) {
            $query->whereDate('submitted_at', '<=', $filters['end_date']);
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return IdentityVerificationResource::collection($items);
    }

    /**
     * 统计数据
     */
    public function stats(): IdentityVerificationStatResource
    {
        return new IdentityVerificationStatResource(IdentityVerificationStatResource::makeStats());
    }

    /**
     * 认证记录详情
     */
    public function show(IdentityVerification $verification): IdentityVerificationResource
    {
        $verification->load(['verifiable', 'admin', 'idCardFront', 'idCardBack', 'handheld', 'businessLicense', 'openingPermit']);

        return new IdentityVerificationResource($verification);
    }

    /**
     * 审核通过
     */
    public function approve(IdentityVerification $verification, Request $request, IdentityVerificationService $service): IdentityVerificationResource
    {
        $this->authorizeAction($verification);

        $service->approve($verification, $request->user('admin'));
        $verification->refresh();
        $verification->load(['verifiable', 'admin', 'idCardFront', 'idCardBack', 'handheld', 'businessLicense', 'openingPermit']);

        return new IdentityVerificationResource($verification);
    }

    /**
     * 审核驳回
     */
    public function reject(RejectIdentityRequest $request, IdentityVerification $verification, IdentityVerificationService $service): IdentityVerificationResource
    {
        $this->authorizeAction($verification);

        $service->reject(
            $verification,
            $request->user('admin'),
            (string) $request->input('reason'),
            $request->input('remark'),
        );
        $verification->refresh();
        $verification->load(['verifiable', 'admin', 'idCardFront', 'idCardBack', 'handheld', 'businessLicense', 'openingPermit']);

        return new IdentityVerificationResource($verification);
    }

    /**
     * 校验当前记录处于可审核状态
     */
    protected function authorizeAction(IdentityVerification $verification): void
    {
        if ($verification->status === ReviewStatus::APPROVED || $verification->status === ReviewStatus::REJECTED) {
            abort(422, __('system.identity_already_reviewed'));
        }
    }
}
