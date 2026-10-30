<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;

/**
 * 后台微信公众号配置控制器（只读）
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class WechatController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:wechat.index');
    }

    /**
     * 获取微信公众号配置（只读）
     */
    public function show(): JsonResponse
    {
        /** @var array<string, mixed> $config */
        $config = config('services.wechat', []);

        return response()->json([
            'app_id' => (string) ($config['app_id'] ?? ''),
            'app_secret' => (string) ($config['app_secret'] ?? ''),
            'token' => (string) ($config['token'] ?? ''),
            'aes_key' => (string) ($config['aes_key'] ?? ''),
            'server_url' => route('wechat.serve'),
        ]);
    }
}
