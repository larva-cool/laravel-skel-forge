<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\System;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Admin\AdminMenu;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Spatie\Permission\Models\Role;
use Stringable;

/**
 * 后台角色列表查询工具。
 *
 * 支持按角色名称关键字筛选，返回角色及其权限点、成员数量。只读操作。
 */
class ListRoles implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'roles.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询后台角色列表。支持按角色名称/显示名称关键字筛选，返回角色信息（含权限点数量与列表、成员数量）。当需要确认某角色拥有哪些权限点、或某权限点应归属哪个角色时使用。只读操作，不能修改角色与权限分配。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配角色名称或显示名称'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $query = Role::query()
            ->where('guard_name', AdminMenu::GUARD_NAME)
            ->with(['permissions' => fn ($q) => $q->select('permissions.id', 'permissions.name')->orderBy('permissions.id')])
            ->withCount(['permissions', 'users']);

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('display_name', 'like', "%{$keyword}%");
            });
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderBy('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'permissions_count' => $role->permissions_count,
            'users_count' => $role->users_count,
            'permissions' => $role->permissions->pluck('name')->values(),
            'created_at' => $role->created_at?->toDateTimeString(),
        ]);

        return json_encode([
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
