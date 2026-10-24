<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User\Address;

use App\Models\User\Address;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * 删除收货地址
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.address.delete')]
#[Description('删除当前登录用户名下指定的收货地址（软删除）。')]
#[IsDestructive]
class DeleteAddressTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $deleted = Address::forUser($request->user()->id)
            ->where('id', $arguments['id'])
            ->delete();

        if (! $deleted) {
            return Response::error('Address not found or not owned by current user.');
        }

        return Response::structured(['message' => 'Address deleted successfully.']);
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
