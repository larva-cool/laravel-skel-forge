<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\ApiServer;
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
use App\Mcp\Tools\User\Notification\MarkAllNotificationsReadTool;
use App\Mcp\Tools\User\Notification\MarkNotificationReadTool;
use App\Mcp\Tools\User\RecordVisitTool;
use App\Mcp\Tools\User\UpdateAvatarTool;
use App\Mcp\Tools\User\UpdateEmailTool;
use App\Mcp\Tools\User\UpdatePasswordTool;
use App\Mcp\Tools\User\UpdatePhoneTool;
use App\Mcp\Tools\User\UpdateProfileTool;
use App\Mcp\Tools\User\UpdateUsernameTool;
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
use App\Mcp\Tools\Wallet\GetRechargeTool;
use App\Mcp\Tools\Wallet\GetWalletOverviewTool;
use App\Mcp\Tools\Wallet\GetWithdrawTool;
use App\Mcp\Tools\Wallet\ListRechargesTool;
use App\Mcp\Tools\Wallet\ListWalletTradesTool;
use App\Mcp\Tools\Wallet\ListWithdrawAccountsTool;
use App\Mcp\Tools\Wallet\ListWithdrawsTool;
use App\Models\Invite\InviteRecord;
use App\Models\Operation\Announcement;
use App\Models\Operation\Banner;
use App\Models\User;
use App\Models\User\Address;
use App\Models\Wallet\Recharge;
use App\Models\Wallet\WithdrawAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * C 端 API MCP Server 功能测试
 */
#[CoversClass(ApiServer::class)]
#[Group('mcp')]
class ApiServerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'mcpuser',
            'email' => 'mcpuser@example.com',
        ])->refresh();
    }

    #[Test]
    #[TestDox('未认证请求 MCP 端点返回 401')]
    public function unauthenticated_request_returns_401(): void
    {
        $this->postJson('/mcp/v1', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertUnauthorized();
    }

    #[Test]
    #[TestDox('认证用户可通过 HTTP 以 Bearer 令牌调用工具')]
    public function authenticated_user_can_call_tool_via_http(): void
    {
        $token = $this->user->createToken('mcp-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/mcp/v1', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'user.profile.get',
                    'arguments' => [],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('result.structuredContent.username', 'mcpuser')
            ->assertJsonPath('result.structuredContent.email', 'mcpuser@example.com');
    }

    #[Test]
    #[TestDox('Server 注册了全部 53 个工具')]
    public function server_registers_all_tools(): void
    {
        ApiServer::tools()->assertRegistered([
            GetUserProfileTool::class,
            ListLoginHistoriesTool::class,
            ListPointsTool::class,
            ListCoinsTool::class,
            ListSocialsTool::class,
            ListVisitsTool::class,
            ListNotificationsTool::class,
            ListUnreadNotificationsTool::class,
            MarkAllNotificationsReadTool::class,
            MarkNotificationReadTool::class,
            ListAddressesTool::class,
            GetAddressTool::class,
            ListMyFeedbacksTool::class,
            ListMyReportsTool::class,
            GetIdentityTool::class,
            GetIdentityStatusTool::class,
            GetWalletOverviewTool::class,
            ListWalletTradesTool::class,
            ListRechargesTool::class,
            GetRechargeTool::class,
            ListWithdrawsTool::class,
            GetWithdrawTool::class,
            ListWithdrawAccountsTool::class,
            ListCommentsTool::class,
            ListCollectionsTool::class,
            ListLikesTool::class,
            ListFollowsTool::class,
            CreateCommentTool::class,
            DeleteCommentTool::class,
            CreateLikeTool::class,
            DeleteLikeTool::class,
            CreateCollectionTool::class,
            DeleteCollectionTool::class,
            CreateFollowTool::class,
            DeleteFollowTool::class,
            ListTasksTool::class,
            ClaimTaskTool::class,
            ListInviteRecordsTool::class,
            GetInviteStatsTool::class,
            ListBannersTool::class,
            ListAreasTool::class,
            ListDictItemsTool::class,
            ListAnnouncementsTool::class,
            GetAnnouncementTool::class,
            GetAgreementTool::class,
            ListTokensTool::class,
            UpdateProfileTool::class,
            UpdateUsernameTool::class,
            UpdatePasswordTool::class,
        ]);
    }

    #[Test]
    #[TestDox('user.profile.get 返回当前用户资料')]
    public function user_profile_get_tool_returns_profile(): void
    {
        ApiServer::actingAs($this->user)
            ->tool(GetUserProfileTool::class)
            ->assertOk()
            ->assertName('user.profile.get')
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('username', 'mcpuser')
                ->where('email', 'mcpuser@example.com')
                ->etc()
            );
    }

    #[Test]
    #[TestDox('wallet.overview.get 返回钱包概览字段')]
    public function wallet_overview_get_tool_returns_overview(): void
    {
        ApiServer::actingAs($this->user)
            ->tool(GetWalletOverviewTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->has('balance')
                ->has('frozen_balance')
                ->has('available_balance')
                ->has('total_balance')
                ->has('total_income')
                ->has('total_expense')
            );
    }

    #[Test]
    #[TestDox('分页工具统一返回 items + pagination 结构')]
    public function paginated_tools_return_unified_structure(): void
    {
        Address::factory()->count(20)->create(['user_id' => $this->user->id]);

        ApiServer::actingAs($this->user)
            ->tool(ListAddressesTool::class, ['page' => 2, 'per_page' => 8])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->has('items', 8)
                ->where('pagination.total', 20)
                ->where('pagination.per_page', 8)
                ->where('pagination.current_page', 2)
                ->where('pagination.last_page', 3)
                ->where('pagination.has_more', true)
            );
    }

    #[Test]
    #[TestDox('user.address.get 越权访问他人地址返回错误')]
    public function get_address_tool_rejects_other_users_address(): void
    {
        $other = User::factory()->create();
        $foreignAddress = Address::factory()->create(['user_id' => $other->id]);

        ApiServer::actingAs($this->user)
            ->tool(GetAddressTool::class, ['id' => $foreignAddress->id])
            ->assertHasErrors();
    }

    #[Test]
    #[TestDox('wallet.recharge.get 越权访问他人订单返回错误')]
    public function get_recharge_tool_rejects_other_users_order(): void
    {
        $other = User::factory()->create();
        $foreignRecharge = Recharge::factory()->forUser($other)->create();

        ApiServer::actingAs($this->user)
            ->tool(GetRechargeTool::class, ['id' => $foreignRecharge->id])
            ->assertHasErrors();
    }

    #[Test]
    #[TestDox('wallet.withdraw_account.list 默认账户排最前')]
    public function list_withdraw_accounts_tool_orders_default_first(): void
    {
        WithdrawAccount::factory()->forUser($this->user)->create(['is_default' => false]);
        WithdrawAccount::factory()->forUser($this->user)->default()->create();

        ApiServer::actingAs($this->user)
            ->tool(ListWithdrawAccountsTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->has('items', 2)
                ->where('items.0.is_default', true)
                ->etc()
            );
    }

    #[Test]
    #[TestDox('common.banner.list 按广告位过滤且仅返回生效中的 Banner')]
    public function list_banners_tool_filters_by_position(): void
    {
        Banner::factory()->create(['position' => 'home_top', 'title' => '首页横幅', 'status' => true]);
        Banner::factory()->create(['position' => 'home_top', 'status' => false]);
        Banner::factory()->create(['position' => 'discover', 'status' => true]);

        ApiServer::actingAs($this->user)
            ->tool(ListBannersTool::class, ['position' => 'home_top'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->has('items', 1)
                ->where('items.0.title', '首页横幅')
                ->etc()
            );
    }

    #[Test]
    #[TestDox('common.banner.list 缺少 position 参数时返回校验错误')]
    public function list_banners_tool_requires_position(): void
    {
        ApiServer::actingAs($this->user)
            ->tool(ListBannersTool::class)
            ->assertHasErrors();
    }

    #[Test]
    #[TestDox('common.announcement.list 仅返回已发布公告')]
    public function list_announcements_tool_only_returns_published(): void
    {
        Announcement::factory()->published()->create(['title' => '已发布公告']);
        Announcement::factory()->draft()->create(['title' => '草稿公告']);

        ApiServer::actingAs($this->user)
            ->tool(ListAnnouncementsTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->has('items', 1)
                ->where('items.0.title', '已发布公告')
                ->etc()
            );
    }

    #[Test]
    #[TestDox('common.announcement.get 不存在的公告返回错误')]
    public function get_announcement_tool_returns_error_for_missing(): void
    {
        ApiServer::actingAs($this->user)
            ->tool(GetAnnouncementTool::class, ['id' => 999])
            ->assertHasErrors();
    }

    #[Test]
    #[TestDox('invite.stats.get 返回邀请统计')]
    public function get_invite_stats_tool_returns_stats(): void
    {
        $invitee = User::factory()->create();
        InviteRecord::factory()->forInviter($this->user)->forInvitee($invitee)->create();

        ApiServer::actingAs($this->user)
            ->tool(GetInviteStatsTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('invitee_count', 1)
                ->where('has_inviter', false)
            );
    }

    #[Test]
    #[TestDox('user.identity.status 未认证时返回未通过')]
    public function get_identity_status_tool_returns_default(): void
    {
        ApiServer::actingAs($this->user)
            ->tool(GetIdentityStatusTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('is_verified', false)
                ->etc()
            );
    }
}
