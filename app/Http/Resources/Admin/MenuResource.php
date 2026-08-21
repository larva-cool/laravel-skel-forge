<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Admin\AdminMenu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 菜单资源
 *
 * @mixin AdminMenu
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MenuResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'path' => $this->path,
            'name' => $this->name,
            'component' => $this->component,
            'redirect' => $this->redirect,
            'title' => $this->title,
            'icon' => $this->icon,
            'link' => $this->link,
            'type' => $this->type,
            'sort' => $this->sort,
            'is_enable' => $this->is_enable,
            'is_hide' => $this->is_hide,
            'is_hide_tab' => $this->is_hide_tab,
            'is_iframe' => $this->is_iframe,
            'keep_alive' => $this->keep_alive,
            'is_full_page' => $this->is_full_page,
            'fixed_tab' => $this->fixed_tab,
            'show_badge' => $this->show_badge,
            'show_text_badge' => $this->show_text_badge,
            'active_path' => $this->active_path,
            'permission' => $this->permission,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
