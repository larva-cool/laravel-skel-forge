<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User\Address;

use App\Http\Resources\Api\V1\AddressResource;
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
 * 获取收货地址详情
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.address.get')]
#[Description('根据地址 ID 获取当前登录用户名下收货地址详情；不存在的地址返回错误。')]
#[IsReadOnly]
#[IsIdempotent]
class GetAddressTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $address = Address::forUser($request->user()->id)->find($arguments['id']);

        if (! $address) {
            return Response::error('Address not found or not owned by current user.');
        }

        return Response::structured((new AddressResource($address))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('地址 ID')->min(1)->required(),
        ];
    }
}
