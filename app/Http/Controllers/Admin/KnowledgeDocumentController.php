<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Knowledge\KnowledgeDocumentIndexRequest;
use App\Http\Requests\Admin\Knowledge\KnowledgeDocumentSaveRequest;
use App\Http\Requests\Admin\Knowledge\KnowledgeSearchRequest;
use App\Http\Resources\Admin\KnowledgeChunkResource;
use App\Http\Resources\Admin\KnowledgeDocumentResource;
use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\KnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台知识库管理控制器（AI 助手 → 知识库）
 *
 * 文档保存后自动派发异步索引任务（分块 + 向量化），
 * 检索测试接口用于验证知识库的语义召回效果。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class KnowledgeDocumentController extends Controller
{
    public function __construct(
        public readonly KnowledgeService $knowledgeService,
    ) {
        $this->middleware('auth:admin');
        $this->middleware('permission:knowledge-documents.index')->only(['index', 'show', 'chunks', 'search']);
        $this->middleware('permission:knowledge-documents.create')->only(['store']);
        $this->middleware('permission:knowledge-documents.edit')->only(['update', 'toggleStatus', 'reindex']);
        $this->middleware('permission:knowledge-documents.delete')->only(['destroy']);
    }

    /**
     * 文档列表（分页）
     */
    public function index(KnowledgeDocumentIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = KnowledgeDocument::query();

        if ($keyword = $request->query('keyword')) {
            $query->where('title', 'like', "%{$keyword}%");
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($request->has('is_enabled')) {
            $query->where('is_enabled', $request->boolean('is_enabled'));
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return KnowledgeDocumentResource::collection($items);
    }

    /**
     * 创建文档（自动派发索引任务）
     */
    public function store(KnowledgeDocumentSaveRequest $request): KnowledgeDocumentResource
    {
        $document = KnowledgeDocument::query()->create($request->validated());

        $this->knowledgeService->queueIndexing($document);

        return new KnowledgeDocumentResource($document);
    }

    /**
     * 文档详情（含原始内容）
     */
    public function show(int $id): KnowledgeDocumentResource
    {
        $document = KnowledgeDocument::query()->findOrFail($id);

        return new KnowledgeDocumentResource($document);
    }

    /**
     * 更新文档（内容变更时自动重建索引）
     */
    public function update(KnowledgeDocumentSaveRequest $request, int $id): KnowledgeDocumentResource
    {
        $document = KnowledgeDocument::query()->findOrFail($id);
        $document->update($request->validated());

        if ($document->wasChanged('content')) {
            $this->knowledgeService->queueIndexing($document);
        }

        return new KnowledgeDocumentResource($document);
    }

    /**
     * 切换启用状态
     */
    public function toggleStatus(int $id): KnowledgeDocumentResource
    {
        $document = KnowledgeDocument::query()->findOrFail($id);
        $document->update(['is_enabled' => ! $document->is_enabled]);

        return new KnowledgeDocumentResource($document);
    }

    /**
     * 重建索引
     */
    public function reindex(int $id): KnowledgeDocumentResource
    {
        $document = KnowledgeDocument::query()->findOrFail($id);

        $this->knowledgeService->queueIndexing($document);

        return new KnowledgeDocumentResource($document->refresh());
    }

    /**
     * 删除文档（分块级联删除）
     */
    public function destroy(int $id): JsonResponse
    {
        $document = KnowledgeDocument::query()->findOrFail($id);
        $document->delete();

        return response()->json(status: 204);
    }

    /**
     * 文档分块列表（分页）
     */
    public function chunks(int $id): AnonymousResourceCollection
    {
        $document = KnowledgeDocument::query()->findOrFail($id);

        $items = $document->chunks()->paginate(per_page(request()));

        return KnowledgeChunkResource::collection($items);
    }

    /**
     * 语义检索测试
     */
    public function search(KnowledgeSearchRequest $request): JsonResponse
    {
        $results = $this->knowledgeService->search(
            $request->string('query')->toString(),
            $request->integer('limit', 5) ?: 5,
        );

        return response()->json($results);
    }
}
