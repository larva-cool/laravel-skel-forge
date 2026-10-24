<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use App\Http\Resources\Api\V1\UserResource;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取当前登录用户基本资料
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.profile.get')]
#[Description('获取当前登录用户的基本资料，包括账号、昵称、头像、积分、金币、会员状态等信息。')]
#[IsReadOnly]
#[IsIdempotent]
class GetUserProfileTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        return Response::structured((new UserResource($request->user()))->resolve());
    }
}
