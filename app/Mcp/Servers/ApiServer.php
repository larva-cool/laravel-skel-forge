<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Common\ListBannersTool;
use App\Mcp\Tools\User\GetUserProfileTool;
use App\Mcp\Tools\Wallet\GetWalletOverviewTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * C 端 API MCP Server
 *
 * 面向 AI 客户端封装 C 端 API 能力，所有操作均以当前认证用户身份执行。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('skel-api')]
#[Version('1.0.0')]
#[Instructions('该 Server 封装了 C 端用户 API 能力，可用于查询当前登录用户的资料、钱包、通知等信息，以及执行内容互动操作。所有工具均以当前认证用户身份执行。')]
class ApiServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        GetUserProfileTool::class,
        GetWalletOverviewTool::class,
        ListBannersTool::class,
    ];
}
