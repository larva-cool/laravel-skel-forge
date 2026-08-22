<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\App\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * App 版本资源（客户端）
 *
 * @mixin AppVersion
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version,
            'build' => $this->build,
            'download_url' => $this->download_url,
            'release_notes' => $this->release_notes,
            'force_update' => $this->force_update,
            'min_supported_build' => $this->min_supported_build,
            'file_size' => $this->file_size,
            'file_hash' => $this->file_hash,
            'published_at' => $this->published_at?->toDateTimeString(),
        ];
    }
}
