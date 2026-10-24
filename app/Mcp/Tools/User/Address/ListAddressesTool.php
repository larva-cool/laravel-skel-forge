<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User\Address;

use App\Http\Resources\Api\V1\AddressResource;
use App\Mcp\Tools\Concerns\BuildsPaginatedResponse;
use App\Models\User\Address;
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
 * 获取当前登录用户的收货地址列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.address.list')]
#[Description('获取当前登录用户的收货地址列表（分页），按创建时间倒序返回。')]
#[IsReadOnly]
#[IsIdempotent]
class ListAddressesTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate($this->paginationRules());

        $paginator = Address::forUser($request->user()->id)
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, AddressResource::class));
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
