<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\App\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * App 版本资源（后台）
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
            'id' => $this->id,
            'app_release_id' => $this->app_release_id,
            'platform' => $this->platform,
            'version' => $this->version,
            'build' => $this->build,
            'download_url' => $this->download_url,
            'release_notes' => $this->release_notes,
            'min_supported_version' => $this->min_supported_version,
            'min_supported_build' => $this->min_supported_build,
            'force_update' => $this->force_update,
            'file_size' => $this->file_size,
            'file_hash' => $this->file_hash,
            'status' => $this->status,
            'published_at' => $this->published_at?->toDateTimeString(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
