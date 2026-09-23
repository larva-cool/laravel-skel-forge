<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReleaseStatus;
use App\Http\Requests\Admin\AppVersion\AppVersionIndexRequest;
use App\Http\Requests\Admin\AppVersion\AppVersionSaveRequest;
use App\Http\Resources\Admin\AppVersionResource;
use App\Models\App\AppRelease;
use App\Models\App\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台版本管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppVersionController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:app-versions.index')->only(['index', 'show']);
        $this->middleware('permission:app-versions.create')->only(['store']);
        $this->middleware('permission:app-versions.edit')->only(['update', 'publish', 'withdraw', 'obsolete']);
        $this->middleware('permission:app-versions.delete')->only(['destroy']);
    }

    /**
     * 版本列表
     */
    public function index(AppVersionIndexRequest $request, int $app): AnonymousResourceCollection
    {
        $versions = AppVersion::query()
            ->where('app_release_id', $app)
            ->filter($request->filters())
            ->orderByDesc('build')
            ->paginate(per_page($request));

        return AppVersionResource::collection($versions);
    }

    /**
     * 创建版本
     */
    public function store(AppVersionSaveRequest $request, int $app): JsonResponse
    {
        $appRelease = AppRelease::query()->findOrFail($app);
        $version = AppVersion::create($request->validated());

        return response()->json(new AppVersionResource($version), 201);
    }

    /**
     * 版本详情
     */
    public function show(int $app, int $version): AppVersionResource
    {
        return new AppVersionResource(
            AppVersion::query()->where('app_release_id', $app)->findOrFail($version)
        );
    }

    /**
     * 更新版本
     */
    public function update(AppVersionSaveRequest $request, int $app, int $version): AppVersionResource
    {
        $appVersion = AppVersion::query()->where('app_release_id', $app)->findOrFail($version);

        $appVersion->update($request->validated());

        return new AppVersionResource($appVersion);
    }

    /**
     * 删除版本
     */
    public function destroy(int $app, int $version): JsonResponse
    {
        $appVersion = AppVersion::query()->where('app_release_id', $app)->findOrFail($version);

        if ($appVersion->status === ReleaseStatus::PUBLISHED) {
            abort(400, __('system.version_published_cannot_delete'));
        }

        $appVersion->delete();

        return response()->json(status: 204);
    }

    /**
     * 发布版本
     */
    public function publish(int $app, int $version): AppVersionResource
    {
        $appVersion = AppVersion::query()->where('app_release_id', $app)->findOrFail($version);
        $appVersion->publish();

        return new AppVersionResource($appVersion);
    }

    /**
     * 撤回版本
     */
    public function withdraw(int $app, int $version): AppVersionResource
    {
        $appVersion = AppVersion::query()->where('app_release_id', $app)->findOrFail($version);

        if (! $appVersion->status->isPublished()) {
            abort(400, __('system.version_only_published_withdraw'));
        }

        $appVersion->withdraw();

        return new AppVersionResource($appVersion);
    }

    /**
     * 废弃版本
     */
    public function obsolete(int $app, int $version): AppVersionResource
    {
        $appVersion = AppVersion::query()->where('app_release_id', $app)->findOrFail($version);

        if ($appVersion->status->isObsolete()) {
            abort(400, __('system.version_already_obsolete'));
        }

        $appVersion->obsolete();

        return new AppVersionResource($appVersion);
    }
}
