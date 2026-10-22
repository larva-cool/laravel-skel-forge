<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Knowledge;

use Stringable;

/**
 * 面向公众号粉丝的公开知识库语义检索工具。
 *
 * 复用 SearchKnowledgeBase 的检索能力，面向微信智能客服等无后台权限体系的场景，
 * 不校验管理员权限。仅检索已启用且完成索引的文档，只读操作。
 */
class SearchPublicKnowledgeBase extends SearchKnowledgeBase
{
    /**
     * 公开场景无权限要求（返回 null 表示放行）。
     */
    public function permission(): string|array|null
    {
        return null;
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '在知识库中进行语义检索。当用户询问平台业务规则、产品功能、操作指南等知识类问题时，用自然语言描述问题进行检索，返回最相关的知识片段。仅检索已完成索引且启用的文档。只读操作。';
    }
}
