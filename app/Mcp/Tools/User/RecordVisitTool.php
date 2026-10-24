<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use App\Http\Resources\Api\V1\VisitResource;
use App\Models\Content\Visit;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 记录一次访问
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('visit.record')]
#[Description('记录当前登录用户对指定资源的一次访问，用于 PV/UV 与最近浏览等场景。')]
class RecordVisitTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate([
            'source_type' => ['required', 'string', 'max:64'],
            'source_id' => ['required', 'integer', 'min:1'],
        ]);

        $visit = Visit::create([
            'user_id' => $request->user()->id,
            'source_type' => $arguments['source_type'],
            'source_id' => $arguments['source_id'],
            'ip' => null,
        ]);

        return Response::structured((new VisitResource($visit))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()->description('访问对象类型（morph 别名）')->max(64)->required(),
            'source_id' => $schema->integer()->description('访问对象 ID')->min(1)->required(),
        ];
    }
}
