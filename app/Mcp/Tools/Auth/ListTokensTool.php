<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Auth;

use App\Http\Resources\Api\V1\TokenResource;
use App\Mcp\Tools\Concerns\BuildsPaginatedResponse;
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
 * 获取当前登录用户已签发的所有访问令牌
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('auth.token.list')]
#[Description('获取当前登录用户已签发的所有 Sanctum 个人访问令牌（分页），按签发时间倒序。')]
#[IsReadOnly]
#[IsIdempotent]
class ListTokensTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate($this->paginationRules());

        /** @var \App\Models\User $user */
        $user = $request->user();

        $paginator = $user->tokens()
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, TokenResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->paginationSchema($schema);
    }
}
