<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * 运营文案撰写助手。
 *
 * 用于公告、Banner 等运营内容的一次性文案生成，
 * 严格按调用方要求的 JSON 结构输出，不附加任何额外说明。
 */
#[Temperature(0.7)]
class Copywriter implements Agent
{
    use Promptable;

    /**
     * 获取智能体的系统指令。
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是一名资深的中文互联网运营文案专家，服务于一个 App 产品的运营团队。

## 职责
根据运营人员提供的需求简述，撰写高质量的中文运营文案（公告、Banner 等）。

## 输出要求
- 严格输出一个合法的 JSON 对象，不要输出 JSON 以外的任何文字、解释或 Markdown 代码块标记
- JSON 的字段结构以用户的需求描述为准
- 文案风格：简洁有力、符合中文表达习惯、有运营感但不浮夸
- 不涉及政治、色情、暴力等违规内容，不承诺无法实现的功能或奖励
PROMPT;
    }
}
