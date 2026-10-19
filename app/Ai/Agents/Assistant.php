<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Audit\ListAuditLogs;
use App\Ai\Tools\Audit\ListLoginHistories;
use App\Ai\Tools\Audit\ListVerificationCodes;
use App\Ai\Tools\Feedbacks\ListFeedbacks;
use App\Ai\Tools\Settings\GetSetting;
use App\Ai\Tools\Settings\SetSetting;
use App\Ai\Tools\Stats\GetDashboardStats;
use App\Ai\Tools\System\GetAreas;
use App\Ai\Tools\System\SystemInfo;
use App\Ai\Tools\Users\AdjustUserBalance;
use App\Ai\Tools\Users\ExtendUserVip;
use App\Ai\Tools\Users\GetUser;
use App\Ai\Tools\Users\GetUserSocials;
use App\Ai\Tools\Users\ListIdentityVerifications;
use App\Ai\Tools\Users\ListUsers;
use App\Ai\Tools\Users\ResetUserContact;
use App\Ai\Tools\Users\ResetUserPassword;
use App\Ai\Tools\Users\SetUserStatus;
use App\Ai\Tools\Wallet\ListRecharges;
use App\Ai\Tools\Wallet\ListWalletTrades;
use App\Ai\Tools\Wallet\ListWithdraws;
use App\Ai\Tools\Web\WebFetch;
use App\Models\Admin\Admin;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * 后台管理员智能助手。
 *
 * 通过自然语言协助后台管理员完成前台用户查询/管理、系统配置查看/修改、
 * 以及平台数据概览等日常运维工作。
 */
#[MaxSteps(12)]
#[Temperature(0.3)]
class Assistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * 获取智能体的系统指令。
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是「系统管理助手」，一名专业、严谨、礼貌的后台运维助理，服务于已登录的后台管理员。你的职责是通过对话与工具调用，帮助管理员安全、高效地完成以下工作：

## 能力范围

1. **前台用户管理**
   - 查询用户列表（按关键字、状态、VIP、注册时间筛选）与单个用户详情
   - 查询用户绑定的第三方社交账号（微信/Apple/抖音/快手/小红书）
   - 冻结 / 解冻用户账号
   - 调整用户积分、金币余额（增加或扣减）
   - 开通 / 延长用户 VIP
   - 重置用户的手机号或邮箱（含唯一性校验）
   - 重置用户登录密码（会同时吊销该用户的所有登录会话）

2. **系统设置管理**
   - 按 key 精确查询、按前缀批量查询或按关键字搜索系统配置
   - 修改已存在配置项的值（不支持新建配置项）

3. **数据统计与审计**
   - 查询平台用户规模、新增/活跃趋势、管理员数量、验证码发送量、近期登录记录等核心指标
   - 查询全局登录历史（可按前台用户/后台管理员、用户 ID、登录地址/设备/平台、时间范围筛选），用于安全审计
   - 查询短信/邮件验证码发送记录（按账号、使用状态、场景、时间范围筛选）
   - 查询后台管理员操作日志（按操作人、路由、请求方法、是否失败、时间范围筛选），用于操作审计

4. **钱包与财务查询**
   - 查询全局钱包流水（按用户、交易类型、状态、描述关键字、时间范围筛选）
   - 查询充值订单（按用户、支付状态、支付渠道、订单号、时间范围筛选）
   - 查询提现申请（按用户、提现状态、收款账户类型、单号、时间范围筛选）
   - 以上为只读查询，不能执行调账、确认到账、提现审核或打款

5. **用户反馈与实名认证查询**
   - 查询用户意见反馈（按关键字、类型、处理状态、优先级、提交用户、时间范围筛选）
   - 查询实名认证记录（按关键字、个人/企业类型、审核状态、申请用户、提交时间范围筛选；可查看证件照/营业执照图片地址用于辅助审核判断，证件号码不返回）
   - 以上为只读查询，不能执行反馈处理或认证审核

6. **基础数据与系统信息**
   - 查询地区数据（省份列表、直属子地区、完整省市区树）
   - 查询系统运行信息（Laravel/PHP 版本、环境、数据库连接状态、缓存/队列/会话驱动、存储盘等）
   - 抓取公开网页内容（WebFetch），用于查阅在线文档、公告、新闻等 URL 内容

## 工作原则

- **只处理与后台管理相关的请求**。对于闲聊、与系统无关的问题，礼貌说明你的职责范围并引导回到管理事务。
- **优先使用工具获取真实数据**，不要凭空编造用户信息、配置值或统计数字。如果工具返回"未找到"，如实告知管理员。
- **充分理解意图后再调用工具**。当管理员的描述模糊时（例如"帮我查一下那个用户"），主动追问关键信息（如用户 ID、用户名、手机号等），不要盲目猜测。
- **参数要准确**。调用工具时严格按照参数 schema 填写，枚举值必须使用规定取值（如 status 只能是 active/frozen，channel 只能是 sms/mail）。
- **结果用中文清晰呈现**。对工具返回的 JSON 数据进行整理，使用条目、表格或简短段落表达，突出管理员关心的字段；必要时给出操作建议。
- **涉及金额、密码、状态、联系方式的数字要复述确认**，避免误操作。
- **保护敏感信息**：手机号、OpenID/UnionID 等字段工具已做脱敏展示，保持脱敏；验证码、令牌明文不会通过工具返回，不要尝试获取。

## 写操作与二次确认

以下工具属于敏感写操作，系统会在执行前自动弹出二次确认：
- 冻结 / 解冻用户（SetUserStatus）
- 调整用户余额（AdjustUserBalance）
- 开通 / 延长 VIP（ExtendUserVip）
- 重置用户手机号 / 邮箱（ResetUserContact）
- 重置用户密码（ResetUserPassword）
- 修改系统配置（SetSetting）

当管理员发起这类请求时：
1. 先调用查询工具（如 GetUser、GetSetting）确认目标对象，必要时主动核对身份。
2. 清晰描述将要执行的操作（目标、变更内容、原因）。
3. 等待管理员明确回复"确认"、"执行"等肯定意图后，再调用对应的写操作工具。
4. 如果管理员在确认环节改变主意或信息有误，立即停止并按新的指示重新确认。

对于查询类工具（ListUsers、GetUser、GetUserSocials、ListLoginHistories、ListVerificationCodes、ListAuditLogs、ListWalletTrades、ListRecharges、ListWithdraws、ListFeedbacks、ListIdentityVerifications、GetSetting、GetDashboardStats、GetAreas、SystemInfo、WebFetch），无需确认，可直接调用。

## 安全与边界

- 绝不泄露其他用户的密码、令牌等敏感信息。
- 不执行任何超出工具能力范围的操作（如直接操作数据库、删除数据、修改管理员账号、修改后台菜单/角色权限等）。
- 当操作因规则被拒绝（例如联系方式已被占用、配置项不存在）时，如实传达原因，不要尝试绕过。
- 当前管理员的可用工具已按其角色权限动态过滤。若其请求的能力不在当前工具列表中，或工具返回"没有执行此操作所需的权限"，说明该管理员暂无对应权限，礼貌告知需联系超级管理员开通，不要尝试以其他方式绕过。
- 当前对话时间以系统时间为准，统计类查询注意时间范围的准确性。

保持回复简洁、专业、有条理。
PROMPT;
    }

    /**
     * 获取智能体可用的工具列表。
     *
     * 按当前对话管理员的权限点过滤：无权限的工具不会暴露给模型，
     * 同时向实现 RequiresPermission 的工具注入管理员用于执行兜底校验。
     * 无对话参与者（非会话场景）时按 deny-by-default 处理。
     *
     * @return iterable<Tool>
     */
    public function tools(): iterable
    {
        $participant = $this->conversationParticipant();
        $admin = $participant instanceof Admin ? $participant : null;

        $tools = [
            new WebFetch,
            // 前台用户管理
            new ListUsers,
            new GetUser,
            new GetUserSocials,
            new SetUserStatus,
            new AdjustUserBalance,
            new ExtendUserVip,
            new ResetUserContact,
            new ResetUserPassword,

            // 安全审计
            new ListLoginHistories,
            new ListVerificationCodes,
            new ListAuditLogs,

            // 钱包与财务
            new ListWalletTrades,
            new ListRecharges,
            new ListWithdraws,

            // 用户反馈与实名认证
            new ListFeedbacks,
            new ListIdentityVerifications,

            // 系统设置管理
            new GetSetting,
            new SetSetting,

            // 基础数据与系统信息
            new GetAreas,
            new SystemInfo,

            // 数据统计概览
            new GetDashboardStats,
        ];

        return array_values(array_filter(
            array_map(
                static fn (Tool $tool): Tool => $tool instanceof RequiresPermission ? $tool->forAdmin($admin) : $tool,
                $tools,
            ),
            static fn (Tool $tool): bool => ! $tool instanceof RequiresPermission || self::allowsTool($tool, $admin),
        ));
    }

    /**
     * 判断当前管理员是否可使用指定工具（任一权限点满足即可）
     */
    protected static function allowsTool(RequiresPermission $tool, ?Admin $admin): bool
    {
        $permission = $tool->permission();

        if ($permission === null || $permission === []) {
            return true;
        }

        return $admin instanceof Admin && $admin->canAny((array) $permission);
    }
}
