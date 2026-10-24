<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use App\Http\Resources\Api\V1\FeedbackResource;
use App\Models\System\Dict;
use App\Models\System\Feedback;
use App\Services\SensitiveWordService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 提交用户反馈
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.feedback.create')]
#[Description('提交用户反馈，支持图片附件与联系方式；标题和内容经过敏感词过滤。')]
class CreateFeedbackTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $typeValues = Dict::query()
            ->where('code', 'feedback_type')
            ->where('status', 1)
            ->with(['dictItems' => fn ($q) => $q->where('status', 1)])
            ->first()
            ?->dictItems
            ->pluck('value')
            ->toArray() ?? [];

        $arguments = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:5000'],
            'images' => ['nullable', 'array', 'max:9'],
            'images.*' => ['required', 'string', 'url', 'max:500'],
            'type' => ['required', 'string', 'max:50', Rule::in($typeValues)],
            'contact_name' => ['nullable', 'string', 'max:50'],
            'contact_info' => ['nullable', 'string', 'max:100'],
        ]);

        // 敏感词处理
        $service = app(SensitiveWordService::class);
        $titleResult = $service->detect((string) $arguments['title']);
        $contentResult = $service->detect((string) $arguments['content']);
        $arguments['title'] = $titleResult->filtered;
        $arguments['content'] = $contentResult->filtered;

        $allHits = array_merge($titleResult->hitWords(), $contentResult->hitWords());
        if ($allHits !== []) {
            try {
                $service->incrementHitCount(array_unique($allHits));
            } catch (\Throwable) {
            }
        }

        $feedback = Feedback::create(array_merge($arguments, [
            'user_id' => $request->user()->id,
            'ip_address' => null,
            'user_agent' => 'MCP',
        ]));

        return Response::structured((new FeedbackResource($feedback))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('反馈标题')->max(200)->required(),
            'content' => $schema->string()->description('详细描述')->max(5000)->required(),
            'images' => $schema->array()->description('反馈图片 URL 列表，最多 9 张'),
            'type' => $schema->string()->description('反馈类型（字典 feedback_type 的 value）')->max(50)->required(),
            'contact_name' => $schema->string()->description('联系人')->max(50),
            'contact_info' => $schema->string()->description('联系方式')->max(100),
        ];
    }
}
