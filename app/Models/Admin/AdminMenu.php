<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Admin;

use App\Enums\MenuType;
use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * 后台菜单模型
 *
 * @property int $id 菜单ID
 * @property int|null $parent_id 父级菜单ID，null 表示顶级菜单
 * @property string|null $path 路由路径
 * @property string|null $name 路由名称
 * @property string|null $component 前端组件路径
 * @property string|null $redirect 重定向路径
 * @property string $title 菜单标题
 * @property string|null $icon 菜单图标
 * @property string|null $link 外部链接地址
 * @property MenuType $type 菜单类型
 * @property int $sort 排序
 * @property bool $is_enable 是否启用
 * @property bool $is_hide 是否在菜单中隐藏
 * @property bool $is_hide_tab 是否在标签页中隐藏
 * @property bool $is_iframe 是否以 iframe 方式内嵌
 * @property bool $keep_alive 是否开启页面缓存
 * @property bool $is_full_page 是否全屏页面
 * @property bool $fixed_tab 是否固定标签页
 * @property bool $show_badge 是否显示红点徽章
 * @property string|null $show_text_badge 文本徽章内容
 * @property string|null $active_path 激活菜单高亮路径
 * @property string|null $permission 权限标识
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 * @property-read AdminMenu|null $parent 父级菜单
 * @property-read Collection<int, AdminMenu> $children 子菜单集合
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[Table('admin_menus')]
#[Fillable([
    'parent_id', 'path', 'name', 'component', 'redirect',
    'title', 'icon', 'link', 'type', 'sort',
    'is_enable', 'is_hide', 'is_hide_tab', 'is_iframe',
    'keep_alive', 'is_full_page', 'fixed_tab', 'show_badge',
    'show_text_badge', 'active_path', 'permission',
])]
class AdminMenu extends Model
{
    /** @var string Spatie 权限使用的 guard 名称 */
    public const string GUARD_NAME = 'admin';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'parent_id' => 'integer',
            'path' => 'string',
            'name' => 'string',
            'component' => 'string',
            'redirect' => 'string',
            'title' => 'string',
            'icon' => 'string',
            'link' => 'string',
            'type' => MenuType::class,
            'sort' => 'integer',
            'is_enable' => 'boolean',
            'is_hide' => 'boolean',
            'is_hide_tab' => 'boolean',
            'is_iframe' => 'boolean',
            'keep_alive' => 'boolean',
            'is_full_page' => 'boolean',
            'fixed_tab' => 'boolean',
            'show_badge' => 'boolean',
            'show_text_badge' => 'string',
            'active_path' => 'string',
            'permission' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        parent::booted();

        // 保存菜单时，同步到 permissions 表
        static::saved(function (self $menu): void {
            $menu->syncPermission();
        });

        // 删除菜单时，同步删除对应 permission
        static::deleted(function (self $menu): void {
            $menu->deletePermission();
        });
    }

    /**
     * 父级菜单
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 子菜单
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort')
            ->with('children');
    }

    /**
     * 作用域：仅启用的菜单
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enable', true);
    }

    /**
     * 作用域：按 parent_id + sort 排序
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort');
    }

    /**
     * 作用域：顶级菜单
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * 判断是否为顶级菜单
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * 判断是否为目录类型
     */
    public function isDirectory(): bool
    {
        return $this->type === MenuType::DIRECTORY;
    }

    /**
     * 判断是否为按钮类型
     */
    public function isButton(): bool
    {
        return $this->type === MenuType::BUTTON;
    }

    /**
     * 获取所有菜单树形结构
     *
     * @return Collection<int, AdminMenu>
     */
    public static function tree(bool $onlyEnabled = false): Collection
    {
        $query = static::query()->with('children')->ordered();

        if ($onlyEnabled) {
            $query->enabled();
        }

        return $query->root()->get();
    }

    /**
     * 转换为前端路由配置格式（AppRouteRecord）
     *
     * 递归处理子菜单，自动：
     * - 过滤禁用菜单（is_enable=false）
     * - 通过 Spatie 权限系统过滤菜单可见性（permission 字段）
     * - 顶级目录 component 设为 "/index/index"（对应 Layout 视图）
     * - 二级目录/页面直接返回数据库存储的 component 路径（如 "/system/admin"）
     * - 按钮类型（type=BUTTON）不返回节点，而是收集到父级 meta.auth_list
     *
     * @param  \App\Models\Admin\Admin|null  $admin  当前管理员，用于权限过滤；null 表示不过滤
     * @return array<string, mixed>|null 返回 null 表示该节点被过滤掉
     */
    public function toRouteRecord(?Admin $admin = null): ?array
    {
        // 过滤禁用菜单
        if (! $this->is_enable) {
            return null;
        }

        // 权限过滤：如果菜单配置了 permission 且当前管理员不具备该权限，排除该节点
        if ($admin !== null && ! blank($this->permission) && ! $admin->can($this->permission)) {
            return null;
        }

        // 按钮类型：返回特殊标记，由父级收集到 auth_list
        if ($this->isButton()) {
            return [
                '__button' => true,
                'title' => $this->title,
                'auth_mark' => $this->permission,
            ];
        }

        // 处理子菜单
        /** @var array<int, array<string, mixed>> $childRecords */
        $childRecords = [];
        /** @var array<int, array{title: string, auth_mark: string}> $authList */
        $authList = [];

        if ($this->children->isNotEmpty()) {
            foreach ($this->children as $child) {
                $result = $child->toRouteRecord($admin);
                if ($result === null) {
                    continue;
                }
                // 按钮节点收集到 auth_list
                if (! empty($result['__button'])) {
                    if (! empty($result['auth_mark'])) {
                        $authList[] = [
                            'title' => $result['title'],
                            'auth_mark' => $result['auth_mark'],
                        ];
                    }

                    continue;
                }
                $childRecords[] = $result;
            }
        }

        // 构建 meta
        $meta = array_filter([
            'title' => $this->title,
            'icon' => $this->icon,
            'link' => $this->link,
            'is_hide' => $this->is_hide ?: null,
            'is_hide_tab' => $this->is_hide_tab ?: null,
            'is_iframe' => $this->is_iframe ?: null,
            'keep_alive' => $this->keep_alive ?: null,
            'is_full_page' => $this->is_full_page ?: null,
            'fixed_tab' => $this->fixed_tab ?: null,
            'show_badge' => $this->show_badge ?: null,
            'show_text_badge' => $this->show_text_badge,
            'active_path' => $this->active_path,
        ], fn ($value): bool => $value !== null);

        // 按钮权限附加到 meta
        if (! empty($authList)) {
            $meta['auth_list'] = $authList;
        }

        // 确定 component：
        // - 顶级目录（parent_id=null 且有子菜单）→ "/index/index" (Layout)
        // - 顶级但无子菜单的页面 → "/index/index" (前端 RouteTransformer 会自动处理)
        // - 非顶级节点 → 数据库存储的 component 值
        $component = $this->isRoot() ? '/index/index' : $this->component;

        $record = [
            'id' => $this->id,
            'path' => $this->path,
            'name' => $this->name,
            'component' => $component,
            'redirect' => $this->redirect,
            'meta' => $meta,
        ];

        // 重定向推导：目录且无显式 redirect 时，取第一个可导航子节点的全路径
        if (empty($record['redirect']) && ! empty($childRecords)) {
            $record['redirect'] = $this->resolveRedirect($this->path, $childRecords);
        }

        if (! empty($childRecords)) {
            $record['children'] = $childRecords;
        } elseif (! $this->isDirectory() && empty($this->component) && $this->type !== MenuType::LINK && ! $this->is_iframe) {
            // 非目录、无子节点、无 component、非外链、非 iframe → 空节点，过滤掉
            return null;
        }

        return array_filter($record, fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * 从子节点中推导默认重定向路径
     *
     * @param  array<int, array<string, mixed>>  $children
     */
    private function resolveRedirect(string $parentPath, array $children): ?string
    {
        foreach ($children as $child) {
            // 外链和 iframe 不作为重定向目标
            if (! empty($child['meta']['link']) || ! empty($child['meta']['is_iframe'])) {
                continue;
            }
            $childPath = $child['path'] ?? '';
            if ($childPath === '') {
                continue;
            }
            // 子路径以 / 开头为绝对路径，否则拼接父路径
            $fullPath = str_starts_with($childPath, '/')
                ? $childPath
                : rtrim($parentPath, '/').'/'.ltrim($childPath, '/');

            // 如果子节点还有子节点，继续深入
            if (! empty($child['children'])) {
                $deep = $this->resolveRedirect($fullPath, $child['children']);
                if ($deep !== null) {
                    return $deep;
                }
            }

            return $fullPath;
        }

        return null;
    }

    /**
     * 将菜单同步到 permissions 表
     *
     * 任何配置了 permission 标识的菜单都会同步到 Spatie permissions 表，
     * 以便通过角色-权限关联统一控制菜单可见性。
     */
    protected function syncPermission(): void
    {
        // 权限标识为空：删除历史 permission（如果存在）
        if (blank($this->permission)) {
            $this->deletePermission();

            return;
        }

        // 权限标识变更时：清理旧权限（如果存在且未被其他菜单引用）
        $originalPermission = $this->getOriginal('permission');
        if (! blank($originalPermission) && $originalPermission !== $this->permission) {
            $this->deletePermission();
        }

        $permission = Permission::findOrCreate($this->permission, self::GUARD_NAME);

        if ($permission->display_name !== $this->title) {
            $permission->update(['display_name' => $this->title]);
        }
    }

    /**
     * 删除对应 permission（仅删除孤立的、未被其他菜单引用且未分配给角色的 permission）
     */
    protected function deletePermission(): void
    {
        if (blank($this->getOriginal('permission'))) {
            return;
        }

        $oldPermission = $this->getOriginal('permission');

        // 其他菜单仍在使用该权限标识时不删除
        $stillUsed = static::query()
            ->where('id', '!=', $this->id)
            ->where('permission', $oldPermission)
            ->exists();

        if ($stillUsed) {
            return;
        }

        $permission = Permission::findByName($oldPermission, self::GUARD_NAME);
        $permission?->delete();
    }
}
