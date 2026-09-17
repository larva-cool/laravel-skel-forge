<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\IdentityStatus;
use App\Models\User\IdentityVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 实名认证统计资源
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdentityVerificationStatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{total: int, pending: int, today: int, approved: int, rejected: int} $data */
        $data = $this->resource;

        return [
            'total' => $data['total'],
            'pending' => $data['pending'],
            'today' => $data['today'],
            'approved' => $data['approved'],
            'rejected' => $data['rejected'],
        ];
    }

    /**
     * 查询统计数据
     *
     * @return array{total: int, pending: int, today: int, approved: int, rejected: int}
     */
    public static function makeStats(): array
    {
        return [
            'total' => IdentityVerification::query()->count(),
            'pending' => IdentityVerification::query()->where('status', IdentityStatus::PENDING->value)->count(),
            'today' => IdentityVerification::query()->whereDate('submitted_at', now()->toDateString())->count(),
            'approved' => IdentityVerification::query()->where('status', IdentityStatus::APPROVED->value)->count(),
            'rejected' => IdentityVerification::query()->where('status', IdentityStatus::REJECTED->value)->count(),
        ];
    }
}
