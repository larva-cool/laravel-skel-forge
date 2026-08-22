<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\App\CheckUpdateRequest;
use App\Http\Resources\Api\V1\AppVersionResource;
use App\Models\App\AppRelease;
use App\Models\App\AppVersion;
use Illuminate\Http\JsonResponse;

/**
 * 客户端 App 版本接口
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppController extends Controller
{
    /**
     * 检查版本更新
     */
    public function checkUpdate(CheckUpdateRequest $request): JsonResponse
    {
        $platform = AppPlatform::from($request->platform);

        $app = AppRelease::query()
            ->active()
            ->where('slug', $request->slug)
            ->first();

        if ($app === null) {
            return response()->json([
                'has_update' => false,
                'force_update' => false,
                'latest_version' => null,
            ]);
        }

        $latestVersion = AppVersion::query()
            ->latestForPlatform($app->id, $platform)
            ->first();

        if ($latestVersion === null || ! $latestVersion->isNewerThan($request->build)) {
            return response()->json([
                'has_update' => false,
                'force_update' => false,
                'latest_version' => null,
            ]);
        }

        $forceUpdate = $latestVersion->requiresForceUpdate($request->build);

        return response()->json([
            'has_update' => true,
            'force_update' => $forceUpdate,
            'latest_version' => new AppVersionResource($latestVersion),
        ]);
    }
}
