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

/**
 * 添加收货地址
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.address.create')]
#[Description('为当前登录用户添加收货地址。若设为默认，将自动取消其他地址的默认标记。')]
class CreateAddressTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:32'],
            'country' => ['sometimes', 'string', 'max:2'],
            'phone' => ['required', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
            'province' => ['required', 'string', 'max:32'],
            'city' => ['required', 'string', 'max:32'],
            'district' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:128'],
            'zipcode' => ['nullable', 'numeric'],
        ]);

        $address = Address::create(array_merge($arguments, [
            'user_id' => $request->user()->id,
            'country' => $arguments['country'] ?? 'CN',
            'is_default' => $arguments['is_default'] ?? false,
        ]));

        if ($address->is_default) {
            $address->markDefault();
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
            'name' => $schema->string()->description('收货人姓名')->min(1)->max(32)->required(),
            'country' => $schema->string()->description('国家码，默认 CN')->max(2),
            'phone' => $schema->string()->description('手机号')->max(20)->required(),
            'is_default' => $schema->boolean()->description('是否设为默认地址'),
            'province' => $schema->string()->description('省')->max(32)->required(),
            'city' => $schema->string()->description('市')->max(32)->required(),
            'district' => $schema->string()->description('区县')->max(32)->required(),
            'address' => $schema->string()->description('详细地址')->max(128)->required(),
            'zipcode' => $schema->integer()->description('邮编'),
        ];
    }
}
