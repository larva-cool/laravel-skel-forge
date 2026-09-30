<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 举报统计资源
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReportStatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, int> $data */
        $data = $this->resource;

        return [
            'total' => $data['total'] ?? 0,
            'pending' => $data['pending'] ?? 0,
            'today' => $data['today'] ?? 0,
            'closed' => $data['closed'] ?? 0,
        ];
    }

    /**
     * 查询举报统计数据
     *
     * @return array<string, int>
     */
    public static function makeStats(): array
    {
        return app(ReportService::class)->stats();
    }
}
