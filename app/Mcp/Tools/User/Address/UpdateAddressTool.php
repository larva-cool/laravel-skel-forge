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
 * 修改收货地址
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.address.update')]
#[Description('修改当前登录用户名下指定的收货地址。')]
class UpdateAddressTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'min:1', 'max:32'],
            'country' => ['sometimes', 'string', 'max:2'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
            'province' => ['sometimes', 'string', 'max:32'],
            'city' => ['sometimes', 'string', 'max:32'],
            'district' => ['sometimes', 'string', 'max:32'],
            'address' => ['sometimes', 'string', 'max:128'],
            'zipcode' => ['sometimes', 'nullable', 'numeric'],
        ]);

        $address = Address::forUser($request->user()->id)->find($arguments['id']);

        if (! $address) {
            return Response::error('Address not found or not owned by current user.');
        }

        $address->update(array_filter($arguments, fn ($key) => $key !== 'id', ARRAY_FILTER_USE_KEY));

        if (! empty($arguments['is_default'])) {
            $address->markDefault();
        }

        return Response::structured((new AddressResource($address->fresh()))->resolve());
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
            'name' => $schema->string()->description('收货人姓名')->min(1)->max(32),
            'country' => $schema->string()->description('国家码')->max(2),
            'phone' => $schema->string()->description('手机号')->max(20),
            'is_default' => $schema->boolean()->description('是否设为默认地址'),
            'province' => $schema->string()->description('省')->max(32),
            'city' => $schema->string()->description('市')->max(32),
            'district' => $schema->string()->description('区县')->max(32),
            'address' => $schema->string()->description('详细地址')->max(128),
            'zipcode' => $schema->integer()->description('邮编'),
        ];
    }
}
