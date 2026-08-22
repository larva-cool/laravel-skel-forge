<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AppRelease\AppReleaseIndexRequest;
use App\Http\Requests\Admin\AppRelease\AppReleaseSaveRequest;
use App\Http\Resources\Admin\AppReleaseResource;
use App\Models\App\AppRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台应用管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppReleaseController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:apps.index')->only(['index', 'show']);
        $this->middleware('permission:apps.create')->only(['store']);
        $this->middleware('permission:apps.edit')->only(['update']);
        $this->middleware('permission:apps.delete')->only(['destroy']);
    }

    /**
     * 应用列表
     */
    public function index(AppReleaseIndexRequest $request): AnonymousResourceCollection
    {
        $apps = AppRelease::query()
            ->with(['latestVersions' => fn ($q) => $q->limit(3)])
            ->filter($request->filters())
            ->orderByDesc('sort')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return AppReleaseResource::collection($apps);
    }

    /**
     * 创建应用
     */
    public function store(AppReleaseSaveRequest $request): JsonResponse
    {
        $app = AppRelease::create($request->validated());

        return response()->json(new AppReleaseResource($app), 201);
    }

    /**
     * 应用详情
     */
    public function show(int $id): AppReleaseResource
    {
        return new AppReleaseResource(AppRelease::query()->findOrFail($id));
    }

    /**
     * 更新应用
     */
    public function update(AppReleaseSaveRequest $request, int $id): AppReleaseResource
    {
        $app = AppRelease::query()->findOrFail($id);
        $app->update($request->validated());

        return new AppReleaseResource($app);
    }

    /**
     * 删除应用
     */
    public function destroy(int $id): JsonResponse
    {
        $app = AppRelease::query()->findOrFail($id);
        $app->delete();

        return response()->json(status: 204);
    }
}
