<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Menu;

use App\Enums\MenuType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 菜单保存请求（创建/编辑）
 *
 * @property-read int|null $parent_id 父级菜单ID，null 表示顶级菜单
 * @property-read string|null $path 路由路径
 * @property-read string|null $name 路由名称
 * @property-read string|null $component 前端组件路径
 * @property-read string|null $redirect 重定向路径
 * @property-read string $title 菜单标题
 * @property-read string|null $icon 菜单图标
 * @property-read string|null $link 外部链接地址
 * @property-read int $type 菜单类型
 * @property-read int $sort 排序
 * @property-read bool $is_enable 是否启用
 * @property-read bool $is_hide 是否在菜单中隐藏
 * @property-read bool $is_hide_tab 是否在标签页中隐藏
 * @property-read bool $is_iframe 是否以 iframe 方式内嵌
 * @property-read bool $keep_alive 是否开启页面缓存
 * @property-read bool $is_full_page 是否全屏页面
 * @property-read bool $fixed_tab 是否固定标签页
 * @property-read bool $show_badge 是否显示红点徽章
 * @property-read string|null $show_text_badge 文本徽章内容
 * @property-read string|null $active_path 激活菜单高亮路径
 * @property-read string|null $permission 权限标识
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MenuSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $menu = $this->route('menu');
        $menuId = $menu instanceof \App\Models\Admin\AdminMenu ? $menu->id : (int) $menu;

        return [
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'path' => ['nullable', 'string', 'max:255'],
            'name' => [
                'nullable', 'string', 'max:100',
                Rule::unique('admin_menus', 'name')->ignore($menuId),
            ],
            'component' => ['nullable', 'string', 'max:255'],
            'redirect' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'link' => ['nullable', 'string', 'max:255', 'url'],
            'type' => ['required', 'integer', Rule::enum(MenuType::class)],
            'sort' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_enable' => ['required', 'boolean'],
            'is_hide' => ['required', 'boolean'],
            'is_hide_tab' => ['required', 'boolean'],
            'is_iframe' => ['required', 'boolean'],
            'keep_alive' => ['required', 'boolean'],
            'is_full_page' => ['required', 'boolean'],
            'fixed_tab' => ['required', 'boolean'],
            'show_badge' => ['required', 'boolean'],
            'show_text_badge' => ['nullable', 'string', 'max:50'],
            'active_path' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:100'],
        ];
    }
}
