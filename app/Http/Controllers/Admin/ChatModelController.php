<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ChatModel\ChatModelIndexRequest;
use App\Http\Requests\Admin\ChatModel\ChatModelSaveRequest;
use App\Http\Resources\Admin\ChatModelResource;
use App\Models\Ai\ChatModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台 AI 模型管理控制器（AI 助手 → 模型管理）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ChatModelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:chat-models.index')->only(['index', 'show']);
        $this->middleware('permission:chat-models.create')->only(['store']);
        $this->middleware('permission:chat-models.edit')->only(['update', 'toggleStatus']);
        $this->middleware('permission:chat-models.delete')->only(['destroy']);
    }

    /**
     * 模型列表（分页）
     */
    public function index(ChatModelIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = ChatModel::query();

        if ($keyword = $request->query('keyword')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('model_id', 'like', "%{$keyword}%"));
        }
        if ($provider = $request->query('provider')) {
            $query->where('provider', $provider);
        }
        if ($request->has('is_enabled')) {
            $query->where('is_enabled', $request->boolean('is_enabled'));
        }

        $items = $query->orderBy('sort')->orderBy('id')->paginate($perPage);

        return ChatModelResource::collection($items);
    }

    /**
     * 创建模型
     */
    public function store(ChatModelSaveRequest $request): ChatModelResource
    {
        $model = ChatModel::query()->create($request->validated());

        return new ChatModelResource($model);
    }

    /**
     * 模型详情
     */
    public function show(int $id): ChatModelResource
    {
        $model = ChatModel::query()->findOrFail($id);

        return new ChatModelResource($model);
    }

    /**
     * 更新模型
     */
    public function update(ChatModelSaveRequest $request, int $id): ChatModelResource
    {
        $model = ChatModel::query()->findOrFail($id);
        $model->update($request->validated());

        return new ChatModelResource($model);
    }

    /**
     * 切换启用状态
     */
    public function toggleStatus(int $id): ChatModelResource
    {
        $model = ChatModel::query()->findOrFail($id);
        $model->update(['is_enabled' => ! $model->is_enabled]);

        return new ChatModelResource($model);
    }

    /**
     * 删除模型
     */
    public function destroy(int $id): JsonResponse
    {
        $model = ChatModel::query()->findOrFail($id);
        $model->delete();

        return response()->json(status: 204);
    }
}
