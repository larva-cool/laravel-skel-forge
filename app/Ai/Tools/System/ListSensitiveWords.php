<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\System;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\System\SensitiveWord;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 敏感词列表查询工具。
 *
 * 支持按词语关键字、分类、命中级别、启用状态筛选，返回分页敏感词数据。只读操作。
 */
class ListSensitiveWords implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'sensitive-words.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询敏感词库列表。支持按词语关键字、分类（涉政/涉黄/暴力/广告/辱骂/违法/其他）、命中级别（自动替换/人工审核/直接拦截）、启用状态筛选，以分页形式返回敏感词信息（含命中次数、替换字符等）。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配敏感词'),
            'category' => $schema->string()
                ->enum(['political', 'pornography', 'violence', 'ad', 'insult', 'illegal', 'other'])
                ->description('分类筛选：political=涉政，pornography=涉黄，violence=暴力，ad=广告，insult=辱骂，illegal=违法，other=其他'),
            'level' => $schema->string()
                ->enum(['replace', 'review', 'block'])
                ->description('命中级别筛选：replace=自动替换，review=人工审核，block=直接拦截'),
            'status' => $schema->boolean()
                ->description('启用状态筛选：true=仅启用，false=仅禁用。不传则查询全部'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $query = SensitiveWord::query();

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where('word', 'like', "%{$keyword}%");
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if ($level = $request->string('level')->toString()) {
            $query->where('level', $level);
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (SensitiveWord $word) => [
            'id' => $word->id,
            'word' => $word->word,
            'category' => $word->category->value,
            'category_label' => $word->category->label(),
            'level' => $word->level->value,
            'level_label' => $word->level->label(),
            'replacement' => $word->replacement,
            'hit_count' => $word->hit_count,
            'status' => $word->status,
            'created_at' => $word->created_at?->toDateTimeString(),
        ]);

        return json_encode([
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
