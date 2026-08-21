<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Common\MailCaptchaRequest;
use App\Http\Requests\Api\V1\Common\SmsCaptchaRequest;
use App\Models\System\Area;
use App\Services\MailCaptchaService;
use App\Services\SmsCaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 公共接口
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CommonController extends Controller
{
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
}
