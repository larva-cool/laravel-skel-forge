<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\Audit\ListLoginHistories;
use App\Ai\Tools\Audit\ListVerificationCodes;
use App\Ai\Tools\Settings\GetSetting;
use App\Ai\Tools\Settings\SetSetting;
use App\Ai\Tools\Stats\GetDashboardStats;
use App\Ai\Tools\System\GetAreas;
use App\Ai\Tools\System\SystemInfo;
use App\Ai\Tools\Users\AdjustUserBalance;
use App\Ai\Tools\Users\ExtendUserVip;
use App\Ai\Tools\Users\GetUser;
use App\Ai\Tools\Users\GetUserSocials;
use App\Ai\Tools\Users\ListUsers;
use App\Ai\Tools\Users\ResetUserContact;
use App\Ai\Tools\Users\ResetUserPassword;
use App\Ai\Tools\Users\SetUserStatus;
use App\Ai\Tools\Web\WebFetch;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
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
#[Model('ep-20260809190412-bkx2g')]
#[Provider('volc')]
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

4. **基础数据与系统信息**
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

对于查询类工具（ListUsers、GetUser、GetUserSocials、ListLoginHistories、ListVerificationCodes、GetSetting、GetDashboardStats、GetAreas、SystemInfo、WebFetch），无需确认，可直接调用。

## 安全与边界

- 绝不泄露其他用户的密码、令牌等敏感信息。
- 不执行任何超出工具能力范围的操作（如直接操作数据库、删除数据、修改管理员账号、修改后台菜单/角色权限等）。
- 当操作因规则被拒绝（例如联系方式已被占用、配置项不存在）时，如实传达原因，不要尝试绕过。
- 当前对话时间以系统时间为准，统计类查询注意时间范围的准确性。

保持回复简洁、专业、有条理。
PROMPT;
    }

    /**
     * 获取智能体可用的工具列表。
     *
     * @return iterable<Tool>
     */
    public function tools(): iterable
    {
        return [
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

            // 系统设置管理
            new GetSetting,
            new SetSetting,

            // 基础数据与系统信息
            new GetAreas,
            new SystemInfo,

            // 数据统计概览
            new GetDashboardStats,
        ];
    }
}
