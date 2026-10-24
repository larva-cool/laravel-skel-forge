<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\ApiServer;
use App\Mcp\Tools\Common\ListBannersTool;
use App\Mcp\Tools\User\GetUserProfileTool;
use App\Mcp\Tools\Wallet\GetWalletOverviewTool;
use App\Models\Operation\Banner;
use App\Models\User;
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
#[CoversClass(GetUserProfileTool::class)]
#[CoversClass(GetWalletOverviewTool::class)]
#[CoversClass(ListBannersTool::class)]
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
    #[TestDox('Server 注册了全部打样工具')]
    public function server_registers_all_tools(): void
    {
        ApiServer::tools()->assertRegistered([
            GetUserProfileTool::class,
            GetWalletOverviewTool::class,
            ListBannersTool::class,
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
}
