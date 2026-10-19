<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\System;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Models\System\SensitiveWord;
use App\Services\SensitiveWordService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 敏感词新增工具。
 *
 * 新增敏感词后自动刷新检测词典缓存使其立即生效。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class AddSensitiveWord implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'sensitive-words.create';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '向敏感词库新增一个敏感词。可指定分类与命中级别（replace=自动替换，review=人工审核，block=直接拦截），新增后自动刷新检测词典缓存立即生效。该操作为敏感写操作，执行前会展示新增摘要并请求二次确认。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'word' => $schema->string()
                ->required()
                ->max(100)
                ->description('敏感词内容'),
            'category' => $schema->string()
                ->enum(['political', 'pornography', 'violence', 'ad', 'insult', 'illegal', 'other'])
                ->description('分类：political=涉政，pornography=涉黄，violence=暴力，ad=广告，insult=辱骂，illegal=违法，other=其他。默认 other'),
            'level' => $schema->string()
                ->enum(['replace', 'review', 'block'])
                ->description('命中级别：replace=自动替换，review=人工审核，block=直接拦截。默认 review'),
            'replacement' => $schema->string()
                ->max(10)
                ->description('替换字符，仅 level=replace 时生效，默认 *'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $category = SensitiveCategory::tryFrom($request->string('category')->toString()) ?? SensitiveCategory::OTHER;
        $level = SensitiveLevel::tryFrom($request->string('level')->toString()) ?? SensitiveLevel::REVIEW;

        return Approval::required("即将新增敏感词「{$request->string('word')->toString()}」（分类：{$category->label()}，命中级别：{$level->label()}），新增后立即生效。");
    }

    /**
     * 执行新增。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $wordText = trim($request->string('word')->toString());
        if ($wordText === '') {
            return '敏感词内容不能为空，操作已取消。';
        }

        $exists = SensitiveWord::query()->where('word', $wordText)->exists();
        if ($exists) {
            return "敏感词「{$wordText}」已存在（不含已删除），无需重复添加。如需修改分类或级别，请到后台敏感词管理中编辑。";
        }

        $category = SensitiveCategory::tryFrom($request->string('category')->toString()) ?? SensitiveCategory::OTHER;
        $level = SensitiveLevel::tryFrom($request->string('level')->toString()) ?? SensitiveLevel::REVIEW;
        $replacement = $request->string('replacement')->toString() ?: '*';

        $word = SensitiveWord::query()->create([
            'word' => $wordText,
            'category' => $category,
            'level' => $level,
            'replacement' => $replacement,
        ]);

        app(SensitiveWordService::class)->refreshDict();

        return "已新增敏感词「{$word->word}」（分类：{$word->category->label()}，命中级别：{$word->level->label()}），检测词典已刷新并即时生效。";
    }
}
