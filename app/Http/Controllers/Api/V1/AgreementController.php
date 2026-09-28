<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AgreementResource;
use App\Models\Operation\Agreement;
use Illuminate\Http\JsonResponse;

/**
 * 前台公开协议接口
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AgreementController extends Controller
{
    /**
     * 按类别获取最新已发布的协议
     *
     * 前端只需传入类别编码（如 user_agreement），直接返回该类别最新已发布版本的完整内容。
     */
    public function showByCategory(string $category): JsonResponse
    {
        $agreement = Agreement::getPublishedByCategory($category);

        if (! $agreement) {
            return response()->json(['message' => '协议不存在或未发布'], 404);
        }

        return (new AgreementResource($agreement))
            ->response()
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
