<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\AppPlatform;
use App\Models\App\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * 应用资源
 *
 * @mixin AppRelease
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppReleaseResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'sort' => $this->sort,
            'status' => $this->status,
            'latest_versions' => $this->whenLoaded('latestVersions', fn () => $this->formatLatestVersions($this->latestVersions)),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * 格式化各平台最新发布版本
     *
     * @param  Collection<int, \App\Models\App\AppVersion>  $versions
     * @return array<string, array<string, mixed>>
     */
    private function formatLatestVersions(Collection $versions): array
    {
        $result = [];
        foreach (AppPlatform::cases() as $platform) {
            $version = $versions->firstWhere('platform', $platform);
            $result[$platform->value] = $version !== null ? [
                'version' => $version->version,
                'build' => $version->build,
                'force_update' => $version->force_update,
                'published_at' => $version->published_at?->toDateTimeString(),
            ] : null;
        }

        return $result;
    }
}
