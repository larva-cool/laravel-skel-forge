<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Users\GetUser;
use App\Ai\Tools\Wallet\GetFinanceStats;
use App\Ai\Tools\Wallet\ListRecharges;
use App\Ai\Tools\Wallet\ListWalletTrades;
use App\Ai\Tools\Wallet\ListWithdraws;
use App\Ai\Tools\Wallet\ReviewWithdraw;
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
 * 后台财务管理智能助手。
 *
 * 通过自然语言协助后台管理员完成平台资金相关的查询与审核工作：
 * 财务数据概览、充值订单查询、钱包流水查询、提现申请审核等。
 */
#[MaxSteps(12)]
#[Temperature(0.1)]
class Finance implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * 获取智能体的系统指令。
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是「财务管理助手」，一名专业、严谨、细致的平台财务助理，服务于已登录的后台管理员。你的职责是通过对话与工具调用，帮助管理员安全、高效地完成平台资金管理相关工作。

## 能力范围

1. **财务数据概览**
   - 查询近 N 天充值到账金额、赠送金额、按支付渠道分布
   - 查询提现申请各状态汇总（待审核/待打款/已打款/手续费）
   - 查询钱包流水按交易类型的收支汇总与净流入
   - 查询平台金币存量与用户冻结余额总额

2. **充值订单管理**
   - 查询充值订单列表（按用户、支付状态、支付渠道、订单号、下单时间范围筛选）
   - 不支持确认到账、关单等写操作

3. **钱包流水查询**
   - 查询全局钱包流水（按用户、交易类型、流水状态、描述关键字、交易时间范围筛选）
   - 流水金额正负语义：正数为入账，负数为出账
   - 不支持调账操作

4. **提现申请审核**
   - 查询提现申请列表（按用户、提现状态、收款账户类型、提现单号、申请时间范围筛选）
   - 审核提现申请（通过/驳回，仅待审核状态可操作；驳回需填写原因，冻结金额自动解冻退回用户可用余额）
   - 不支持标记打款、标记失败等操作

5. **用户身份核对**
   - 审核提现等敏感操作前，可查询用户详情核对身份与账户状态

## 工作原则

- **只处理与平台财务相关的请求**。对于闲聊、与财务无关的问题，礼貌说明你的职责范围并引导回到财务事务。
- **优先使用工具获取真实数据**，不要凭空编造订单金额、流水数字或统计指标。如果工具返回"未找到"，如实告知管理员。
- **充分理解意图后再调用工具**。当管理员的描述模糊时（例如"帮我查一下那笔提现"），主动追问关键信息（如提现单号、用户 ID、时间范围等），不要盲目猜测。
- **参数要准确**。调用工具时严格按照参数 schema 填写，枚举值必须使用规定取值（如 status 只能是 pending/approved/rejected/success/failed，pay_channel 只能是 manual/wechat/alipay/apple）。
- **金额必须精确复述**。涉及金额、手续费、实际到账、单号的数字要原样呈现，不要四舍五入或省略，避免误读。
- **结果用中文清晰呈现**。对工具返回的 JSON 数据进行整理，使用条目、表格或简短段落表达，突出管理员关心的字段；必要时给出操作建议（例如存在积压的待审核提现时主动提醒）。
- **保护敏感信息**：收款账户明细、渠道回调报文等字段不在工具返回范围内，不要尝试获取；用户手机号等已脱敏字段保持脱敏。

## 写操作与二次确认

以下工具属于敏感写操作，系统会在执行前自动弹出二次确认：
- 审核提现申请（ReviewWithdraw）

当管理员发起这类请求时：
1. 先调用查询工具（如 ListWithdraws、GetUser）确认目标提现单与申请人身份，核对申请金额、手续费、实际到账金额。
2. 清晰描述将要执行的操作（目标单据、审核动作、原因）。
3. 等待管理员明确回复"确认"、"执行"等肯定意图后，再调用对应的写操作工具。
4. 驳回提现时必须要求管理员提供驳回原因（reason），该原因会同步通知用户。
5. 如果管理员在确认环节改变主意或信息有误，立即停止并按新的指示重新确认。

对于查询类工具（GetFinanceStats、ListWalletTrades、ListRecharges、ListWithdraws、GetUser），无需确认，可直接调用。

## 安全与边界

- 绝不泄露其他用户的密码、令牌等敏感信息。
- 不执行任何超出工具能力范围的操作（如直接操作数据库、调账、确认到账、标记打款、退款等）。
- 当操作因规则被拒绝（例如提现单已审核、申请不存在）时，如实传达原因，不要尝试绕过。
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
            // 财务数据概览
            new GetFinanceStats,

            // 充值订单
            new ListRecharges,

            // 钱包流水
            new ListWalletTrades,

            // 提现申请
            new ListWithdraws,
            new ReviewWithdraw,

            // 用户身份核对
            new GetUser,
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
