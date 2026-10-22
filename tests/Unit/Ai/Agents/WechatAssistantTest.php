<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Agents;

use App\Ai\Agents\WechatAssistant;
use App\Ai\Tools\Knowledge\SearchPublicKnowledgeBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 微信公众号智能客服助手测试
 */
#[CoversClass(WechatAssistant::class)]
#[Group('ai')]
#[Group('wechat')]
class WechatAssistantTest extends TestCase
{
    #[Test]
    #[TestDox('系统指令包含知识库检索、回复约束与人工客服引导')]
    public function instructions_contain_key_constraints(): void
    {
        $instructions = (string) (new WechatAssistant)->instructions();

        $this->assertStringContainsString('知识库', $instructions);
        $this->assertStringContainsString('人工客服', $instructions);
        $this->assertStringContainsString('不要编造', $instructions);
        $this->assertStringContainsString('300 字', $instructions);
    }

    #[Test]
    #[TestDox('工具列表仅包含公开知识库检索工具')]
    public function tools_only_contain_public_knowledge_search(): void
    {
        $tools = collect((new WechatAssistant)->tools())->values();

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(SearchPublicKnowledgeBase::class, $tools->first());
    }

    #[Test]
    #[TestDox('智能体可被调用并返回文本回复')]
    public function prompt_returns_text_response(): void
    {
        WechatAssistant::fake(['您好，这里是智能客服。']);

        $response = WechatAssistant::make()->prompt('你好');

        $this->assertSame('您好，这里是智能客服。', (string) $response);
        WechatAssistant::assertPrompted('你好');
    }
}
