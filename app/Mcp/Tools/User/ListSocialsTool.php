<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use App\Http\Resources\Api\V1\SocialResource;
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
 * 获取当前登录用户绑定的第三方社交账号列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.socials.list')]
#[Description('获取当前登录用户绑定的第三方社交账号列表（分页），可按 provider 渠道过滤；openid/unionid 已脱敏。')]
#[IsReadOnly]
#[IsIdempotent]
class ListSocialsTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate(array_merge($this->paginationRules(), [
            'provider' => ['sometimes', 'string', 'max:32'],
        ]));

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = $user->socials();

        if (! empty($arguments['provider'])) {
            $query->where('provider', $arguments['provider']);
        }

        $paginator = $query->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, SocialResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return array_merge($this->paginationSchema($schema), [
            'provider' => $schema->string()->description('可选，第三方渠道标识，如 wechat、apple、google')->max(32),
        ]);
    }
}
