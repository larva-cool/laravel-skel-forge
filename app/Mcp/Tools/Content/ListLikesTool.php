<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Content;

use App\Http\Resources\Api\V1\LikeResource;
use App\Mcp\Tools\Concerns\BuildsPaginatedResponse;
use App\Models\Content\Like;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取当前登录用户的点赞列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('content.like.list')]
#[Description('获取当前登录用户的点赞列表（分页），可按 source_type 过滤。')]
#[IsReadOnly]
#[IsIdempotent]
class ListLikesTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate(array_merge($this->paginationRules(), [
            'source_type' => ['sometimes', 'string', 'max:64'],
        ]));

        $query = Like::forUser($request->user()->id)->orderByDesc('id');

        if (! empty($arguments['source_type'])) {
            $query->where('source_type', $arguments['source_type']);
        }

        $paginator = $query->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, LikeResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return array_merge($this->paginationSchema($schema), [
            'source_type' => $schema->string()->description('可选，点赞对象的来源类型（morph 别名）')->max(64),
        ]);
    }
}
