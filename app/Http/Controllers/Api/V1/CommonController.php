<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Common\MailCaptchaRequest;
use App\Http\Requests\Api\V1\Common\SmsCaptchaRequest;
use App\Http\Resources\Api\V1\PublicDictItemResource;
use App\Models\System\Area;
use App\Models\System\Dict;
use App\Services\MailCaptchaService;
use App\Services\SmsCaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 公共接口
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
        // 注册限速
        $this->middleware('throttle:5,1')->only(['fpm']);
    }

    /**
     * 重载 Fpm
     */
    public function fpm(): JsonResponse
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return response()->json(['message' => __('system.successful_operation')]);
    }


    /**
     * 系统配置
     */
    public function settings(): JsonResponse
    {
        $settings = [
            // 系统基本配置
            'system' => [
                'title' => settings('system.title'),
                'keywords' => settings('system.keywords'),
                'description' => settings('system.description'),
                'icp_beian' => settings('system.icp_beian'),
                'police_beian' => settings('system.police_beian'),
                'support_email' => settings('system.support_email'),
                'support_phone' => settings('system.support_phone'),
                'lawyer_email' => settings('system.lawyer_email'),
                'url' => settings('system.url'),
                'm_url' => settings('system.m_url'),
            ],
            // 用户配置
            'user' => [
                'enable_register' => settings('user.enable_register', true),
                'enable_phone_register' => settings('user.enable_phone_register', true),
                'enable_email_register' => settings('user.enable_email_register', true),
                'enable_wechat_login' => settings('user.enable_wechat_login', true),
                'enable_phone_login' => settings('user.enable_phone_login', true),
                'enable_password_login' => settings('user.enable_password_login', true),
                'enable_change_username' => settings('user.username_change', 0) > 0,
                'username_change' => settings('user.username_change', 0),
            ],
        ];

        return response()->json($settings);
    }

    /**
     * 短信验证码
     */
    public function smsCaptcha(SmsCaptchaRequest $request): JsonResponse
    {
        $verifyCode = SmsCaptchaService::make($request->phone, $request->ip(), $request->scene);

        return response()->json($verifyCode->send());
    }

    /**
     * 邮件验证码
     */
    public function mailCaptcha(MailCaptchaRequest $request): JsonResponse
    {
        $verifyCode = MailCaptchaService::make($request->email, $request->ip());

        return response()->json($verifyCode->send());
    }

    /**
     * 地区接口
     */
    public function area(Request $request): JsonResponse
    {
        $query = Area::query();
        if ($request->filled('area_id')) {
            $query->where('parent_id', (int) $request->query('area_id'));
        } else {
            $query->whereNull('parent_id');
        }

        $items = $query->select(['id', 'name'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return response()->json($items);
    }

    /**
     * 公开字典项接口（白名单控制）
     *
     * 获取前端可公开访问的字典项列表，如反馈类型等。仅返回白名单内的字典编码对应的数据，
     * 使用 Redis 缓存 2 小时，并附加 HTTP 缓存头。
     */
    public function dictItems(string $code): AnonymousResourceCollection
    {
        $items = Dict::getActiveItemsByCode($code);

        return PublicDictItemResource::collection($items);
    }
}
