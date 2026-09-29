<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Http\Requests\Admin\SensitiveWord\SensitiveWordImportRequest;
use App\Http\Requests\Admin\SensitiveWord\SensitiveWordIndexRequest;
use App\Http\Requests\Admin\SensitiveWord\SensitiveWordSaveRequest;
use App\Http\Resources\Admin\SensitiveWordResource;
use App\Models\System\SensitiveWord;
use App\Services\SensitiveWordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * 后台敏感词管理控制器（运营管理 → 内容安全）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SensitiveWordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:sensitive-words.index')->only(['index', 'show', 'options']);
        $this->middleware('permission:sensitive-words.create')->only(['store', 'import']);
        $this->middleware('permission:sensitive-words.edit')->only(['update', 'toggleStatus', 'refresh']);
        $this->middleware('permission:sensitive-words.delete')->only(['destroy']);
    }

    /**
     * 敏感词列表（分页）
     */
    public function index(SensitiveWordIndexRequest $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = SensitiveWord::query();

        if ($keyword = $request->query('keyword')) {
            $query->where('word', 'like', "%{$keyword}%");
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($level = $request->query('level')) {
            $query->where('level', $level);
        }
        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return SensitiveWordResource::collection($items);
    }

    /**
     * 创建敏感词
     */
    public function store(SensitiveWordSaveRequest $request, SensitiveWordService $service): SensitiveWordResource
    {
        $word = SensitiveWord::query()->create($request->validated());
        $service->refreshDict();

        return new SensitiveWordResource($word);
    }

    /**
     * 敏感词详情
     */
    public function show(int $id): SensitiveWordResource
    {
        $word = SensitiveWord::query()->findOrFail($id);

        return new SensitiveWordResource($word);
    }

    /**
     * 更新敏感词
     */
    public function update(
        SensitiveWordSaveRequest $request,
        int $id,
        SensitiveWordService $service
    ): SensitiveWordResource {
        $word = SensitiveWord::query()->findOrFail($id);
        $word->update($request->validated());
        $service->refreshDict();

        return new SensitiveWordResource($word);
    }

    /**
     * 切换启用状态
     */
    public function toggleStatus(int $id, SensitiveWordService $service): SensitiveWordResource
    {
        $word = SensitiveWord::query()->findOrFail($id);
        $word->update(['status' => ! $word->status]);
        $service->refreshDict();

        return new SensitiveWordResource($word);
    }

    /**
     * 删除敏感词
     */
    public function destroy(int $id, SensitiveWordService $service): JsonResponse
    {
        $word = SensitiveWord::query()->findOrFail($id);
        $word->delete();
        $service->refreshDict();

        return response()->json(status: 204);
    }

    /**
     * 批量导入敏感词（支持最多 1000 条/次，自动去重）
     *
     * @return JsonResponse 返回成功数量与跳过列表
     */
    public function import(SensitiveWordImportRequest $request, SensitiveWordService $service): JsonResponse
    {
        $words = $request->validated('words');
        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($words, &$created, &$skipped) {
            $existing = SensitiveWord::withTrashed()
                ->whereIn('word', array_column($words, 'word'))
                ->pluck('word')
                ->flip()
                ->all();

            foreach ($words as $item) {
                if (isset($existing[$item['word']])) {
                    $skipped[] = $item['word'];

                    continue;
                }
                SensitiveWord::query()->create($item);
                $created++;
            }
        });

        if ($created > 0) {
            $service->refreshDict();
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    /**
     * 手动刷新 DFA 字典缓存
     */
    public function refresh(SensitiveWordService $service): JsonResponse
    {
        $service->refreshDict();

        return response()->json([
            'message' => __('admin.sensitive_dict_refreshed'),
            'total' => SensitiveWord::enabled()->count(),
        ]);
    }

    /**
     * 获取分类与级别选项（前端下拉使用）
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'categories' => array_map(
                fn (SensitiveCategory $c) => ['value' => $c->value, 'label' => $c->label()],
                SensitiveCategory::cases()
            ),
            'levels' => array_map(
                fn (SensitiveLevel $l) => ['value' => $l->value, 'label' => $l->label()],
                SensitiveLevel::cases()
            ),
        ]);
    }
}
