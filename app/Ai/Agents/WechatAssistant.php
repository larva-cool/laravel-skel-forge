<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\Knowledge\SearchPublicKnowledgeBase;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * 微信公众号智能客服助手。
 *
 * 面向公众号粉丝的文本消息，基于公开知识库（RAG）自动作答；
 * 知识库未收录的问题引导用户联系人工客服。
 */
#[MaxSteps(4)]
#[Temperature(0.4)]
#[Timeout(300)]
class WechatAssistant implements Agent, HasTools
{
    use Promptable;

    /**
     * 获取智能体的系统指令。
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是微信公众号的智能客服助手，负责回答公众号粉丝的问题。

## 职责
- 优先使用知识库检索工具查找依据，基于检索到的内容回答平台业务规则、产品功能、操作指南类问题
- 检索不到相关内容时，礼貌告知用户该问题暂未收录，并引导其联系人工客服，不要编造答案
- 简单的问候、闲聊可以友好简短回应，无需调用工具

## 回复要求
- 使用口语化、亲切的中文，适合微信聊天阅读
- 不使用 Markdown 格式（如标题、表格、代码块），可用短句与换行组织内容
- 单次回复控制在 300 字以内，重点突出
- 不泄露内部信息（如其他用户数据、系统配置、接口细节）
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
            new SearchPublicKnowledgeBase,
        ];
    }
}
