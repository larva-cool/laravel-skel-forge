<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * 注销并删除当前登录用户的账户
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.delete')]
#[Description('注销并删除当前登录用户的账户（高风险：数据将永久删除，不可恢复）。')]
#[IsDestructive]
class DeleteAccountTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->delete();

        return Response::structured(['message' => 'Account deleted successfully.']);
    }
}
