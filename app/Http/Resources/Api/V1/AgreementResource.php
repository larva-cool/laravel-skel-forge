<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Operation\Agreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 协议前台公开资源
 *
 * @mixin Agreement
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AgreementResource extends JsonResource
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
            'title' => $this->title,
            'category' => $this->category,
            'content' => $this->content,
            'version' => $this->version,
            'effective_at' => $this->effective_at?->toDateTimeString(),
        ];
    }
}
