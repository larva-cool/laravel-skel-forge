<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use App\Http\Resources\Api\V1\ReportResource;
use App\Services\ReportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 提交用户举报
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.report.create')]
#[Description('提交用户举报，目标类型支持 comment / user；同一对象在未结案前不可重复举报。')]
class CreateReportTool extends Tool
{
    private const REPORTABLE_TYPES = ['comment', 'user'];
    private const CATEGORIES = [
        'pornography', 'violence', 'political', 'ad',
        'insult', 'fake', 'infringement', 'illegal', 'other',
    ];

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ReportService $reportService): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'target_type' => ['required', 'string', Rule::in(self::REPORTABLE_TYPES)],
            'target_id' => ['required', 'integer', 'min:1'],
            'category' => ['required', 'string', Rule::in(self::CATEGORIES)],
            'reason' => ['nullable', 'string', 'max:500'],
            'evidence' => ['nullable', 'array', 'max:9'],
            'evidence.*' => ['string', 'url', 'max:500'],
        ]);

        $class = Relation::getMorphedModel($arguments['target_type']);

        if (! $class || ! class_exists($class)) {
            return Response::error('Unsupported target type.');
        }

        $target = $class::query()->find($arguments['target_id']);

        if (! $target) {
            return Response::error('Target not found.');
        }

        $report = $reportService->submit($request->user(), $target, [
            'category' => $arguments['category'],
            'reason' => $arguments['reason'] ?? null,
            'evidence' => $arguments['evidence'] ?? null,
        ]);

        return Response::structured((new ReportResource($report))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_type' => $schema->string()->description('举报对象类型：comment / user')->required(),
            'target_id' => $schema->integer()->description('举报对象 ID')->min(1)->required(),
            'category' => $schema->string()->description('举报分类：pornography/violence/political/ad/insult/fake/infringement/illegal/other')->required(),
            'reason' => $schema->string()->description('举报描述')->max(500),
            'evidence' => $schema->array()->description('证据图片 URL 列表，最多 9 张'),
        ];
    }
}
