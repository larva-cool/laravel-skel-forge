<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;

/**
 * 后台公共接口控制器
 *
 * 提供与业务模块无关的通用选项、枚举、字典等接口。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CommonController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 获取源类型选项列表（基于 morph map）
     */
    public function sourceTypes(): JsonResponse
    {
        $labels = [
            'user' => __('admin.source_type_user'),
            'admin' => __('admin.source_type_admin'),
            'comment' => __('admin.source_type_comment'),
            'point' => __('admin.source_type_point'),
        ];

        $types = [];
        foreach (source_types() as $key) {
            $types[] = ['label' => $labels[$key] ?? $key, 'value' => $key];
        }

        return response()->json($types);
    }
}
