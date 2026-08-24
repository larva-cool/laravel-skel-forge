<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CacheKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Common\MailCaptchaRequest;
use App\Http\Requests\Api\V1\Common\SmsCaptchaRequest;
use App\Http\Resources\Api\V1\PublicDictItemResource;
use App\Models\System\Area;
use App\Models\System\Dict;
use App\Models\System\DictItem;
use App\Services\MailCaptchaService;
use App\Services\SmsCaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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
        // 白名单：仅允许公开访问的字典编码
        $allowedCodes = ['feedback_type'];

        abort_unless(in_array($code, $allowedCodes, true), 404);

        $cacheKey = CacheKey::key(CacheKey::DICT_TYPE, $code);

        // 缓存纯数组而非 Eloquent Collection，避免 Octane/反序列化场景下
        // 出现 "incomplete object" 错误；读取时再 hydrate 为模型集合。
        /** @var array<int, array<string, mixed>> $itemsArray */
        $itemsArray = Cache::remember($cacheKey, Carbon::now()->addHours(2), function () use ($code) {
            $dict = Dict::query()
                ->where('code', $code)
                ->where('status', 1)
                ->first();

            if (! $dict) {
                return [];
            }

            return $dict->dictItems()
                ->where('status', 1)
                ->orderBy('sort')
                ->orderBy('id')
                ->get()
                ->toArray();
        });

        /** @var \Illuminate\Database\Eloquent\Collection<int, DictItem> $items */
        $items = DictItem::hydrate($itemsArray);

        return PublicDictItemResource::collection($items);
    }
}
