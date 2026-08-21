<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Admin\AdminMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * 主控制器
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class MainController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 获取前端路由配置（按当前管理员权限过滤）
     *
     * 返回 AppRouteRecord[] 格式，前端可直接用于动态路由注册
     */
    public function routes(Request $request): JsonResponse
    {
        /** @var \App\Models\Admin\Admin $admin */
        $admin = $request->user();

        // onlyEnabled=true：只返回启用的菜单
        $menus = AdminMenu::tree(true);

        $routes = $menus
            ->map(fn (AdminMenu $menu): ?array => $menu->toRouteRecord($admin))
            ->filter()
            ->values()
            ->all();

        return response()->json($routes);
    }

    /**
     * 清理应用缓存
     *
     * 支持通过 `types` 参数指定清理范围：
     * - `all`：清空整个缓存存储（默认）
     * - `settings`：仅清理系统配置缓存
     */
    public function clearCache(Request $request): JsonResponse
    {
        $types = (array) $request->input('types', ['all']);

        foreach ($types as $type) {
            if ($type === 'settings' || $type === 'all') {
                settings()->clearCache();
            }
        }

        if (in_array('all', $types, true)) {
            Cache::flush();
            // flush 会清掉配置缓存，重新加载以保证当前请求内 settings() 立即可用
            settings()->all(true);
        }

        return response()->json([
            'message' => '缓存清理成功',
            'types' => array_values(array_unique($types)),
        ]);
    }
}
