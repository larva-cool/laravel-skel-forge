<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Auth\ListTokensTool;
use App\Mcp\Tools\Common\GetAgreementTool;
use App\Mcp\Tools\Common\GetAnnouncementTool;
use App\Mcp\Tools\Common\ListAnnouncementsTool;
use App\Mcp\Tools\Common\ListAreasTool;
use App\Mcp\Tools\Common\ListBannersTool;
use App\Mcp\Tools\Common\ListDictItemsTool;
use App\Mcp\Tools\Content\CreateCollectionTool;
use App\Mcp\Tools\Content\CreateCommentTool;
use App\Mcp\Tools\Content\CreateFollowTool;
use App\Mcp\Tools\Content\CreateLikeTool;
use App\Mcp\Tools\Content\DeleteCollectionTool;
use App\Mcp\Tools\Content\DeleteCommentTool;
use App\Mcp\Tools\Content\DeleteFollowTool;
use App\Mcp\Tools\Content\DeleteLikeTool;
use App\Mcp\Tools\Content\ListCollectionsTool;
use App\Mcp\Tools\Content\ListCommentsTool;
use App\Mcp\Tools\Content\ListFollowsTool;
use App\Mcp\Tools\Content\ListLikesTool;
use App\Mcp\Tools\Invite\GetInviteStatsTool;
use App\Mcp\Tools\Invite\ListInviteRecordsTool;
use App\Mcp\Tools\Task\ClaimTaskTool;
use App\Mcp\Tools\Task\ListTasksTool;
use App\Mcp\Tools\User\Address\GetAddressTool;
use App\Mcp\Tools\User\Address\ListAddressesTool;
use App\Mcp\Tools\User\GetUserProfileTool;
use App\Mcp\Tools\User\Identity\GetIdentityStatusTool;
use App\Mcp\Tools\User\Identity\GetIdentityTool;
use App\Mcp\Tools\User\ListCoinsTool;
use App\Mcp\Tools\User\ListLoginHistoriesTool;
use App\Mcp\Tools\User\ListMyFeedbacksTool;
use App\Mcp\Tools\User\ListMyReportsTool;
use App\Mcp\Tools\User\ListPointsTool;
use App\Mcp\Tools\User\ListSocialsTool;
use App\Mcp\Tools\User\ListVisitsTool;
use App\Mcp\Tools\User\Notification\ListNotificationsTool;
use App\Mcp\Tools\User\Notification\ListUnreadNotificationsTool;
use App\Mcp\Tools\User\Notification\MarkAllNotificationsReadTool;
use App\Mcp\Tools\User\Notification\MarkNotificationReadTool;
use App\Mcp\Tools\User\RecordVisitTool;
use App\Mcp\Tools\User\UpdateAvatarTool;
use App\Mcp\Tools\User\UpdateEmailTool;
use App\Mcp\Tools\User\UpdatePasswordTool;
use App\Mcp\Tools\User\UpdatePhoneTool;
use App\Mcp\Tools\User\UpdateProfileTool;
use App\Mcp\Tools\User\UpdateUsernameTool;
use App\Mcp\Tools\Wallet\GetRechargeTool;
use App\Mcp\Tools\Wallet\GetWalletOverviewTool;
use App\Mcp\Tools\Wallet\GetWithdrawTool;
use App\Mcp\Tools\Wallet\ListRechargesTool;
use App\Mcp\Tools\Wallet\ListWalletTradesTool;
use App\Mcp\Tools\Wallet\ListWithdrawAccountsTool;
use App\Mcp\Tools\Wallet\ListWithdrawsTool;
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
#[Instructions('该 Server 封装了 C 端用户 API 能力，可查询当前登录用户的资料、钱包、通知、地址、实名、内容互动、任务、邀请，以及公开的 Banner/公告/协议/字典/地区等数据，并支持修改资料、发布评论/点赞/收藏/关注、领取任务奖励、标记通知已读等操作。所有工具均以当前认证用户身份执行；分页工具统一接受 page/per_page 入参，返回 items + pagination 结构。')]
class ApiServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // 用户资料与日志
        GetUserProfileTool::class,
        ListLoginHistoriesTool::class,
        ListPointsTool::class,
        ListCoinsTool::class,
        ListSocialsTool::class,
        ListVisitsTool::class,
        // 通知
        ListNotificationsTool::class,
        ListUnreadNotificationsTool::class,
        MarkAllNotificationsReadTool::class,
        MarkNotificationReadTool::class,
        // 地址
        ListAddressesTool::class,
        GetAddressTool::class,
        // 反馈 / 举报
        ListMyFeedbacksTool::class,
        ListMyReportsTool::class,
        // 实名认证
        GetIdentityTool::class,
        GetIdentityStatusTool::class,
        // 钱包
        GetWalletOverviewTool::class,
        ListWalletTradesTool::class,
        ListRechargesTool::class,
        GetRechargeTool::class,
        ListWithdrawsTool::class,
        GetWithdrawTool::class,
        ListWithdrawAccountsTool::class,
        // 内容互动（读）
        ListCommentsTool::class,
        ListCollectionsTool::class,
        ListLikesTool::class,
        ListFollowsTool::class,
        // 内容互动（写）
        CreateCommentTool::class,
        DeleteCommentTool::class,
        CreateLikeTool::class,
        DeleteLikeTool::class,
        CreateCollectionTool::class,
        DeleteCollectionTool::class,
        CreateFollowTool::class,
        DeleteFollowTool::class,
        // 任务与邀请
        ListTasksTool::class,
        ClaimTaskTool::class,
        ListInviteRecordsTool::class,
        GetInviteStatsTool::class,
        // 公开数据
        ListBannersTool::class,
        ListAreasTool::class,
        ListDictItemsTool::class,
        ListAnnouncementsTool::class,
        GetAnnouncementTool::class,
        GetAgreementTool::class,
        // 会话
        ListTokensTool::class,
        // 用户资料修改
        UpdateProfileTool::class,
        UpdateUsernameTool::class,
        UpdateEmailTool::class,
        UpdatePhoneTool::class,
        UpdateAvatarTool::class,
        UpdatePasswordTool::class,
        // 访问记录
        RecordVisitTool::class,
    ];
}
