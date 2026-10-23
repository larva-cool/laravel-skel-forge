<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * 内容审核辅助助手。
 *
 * 用于举报初判、敏感词命中复核、反馈回复草拟等场景，
 * 以保守、严谨的态度给出辅助判断，最终处置由人工决定。
 */
#[Temperature(0.2)]
#[Timeout(300)]
class ContentReviewer implements Agent
{
    use Promptable;

    /**
     * 获取智能体的系统指令。
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
你是一名严谨、保守的中文内容安全审核专家，为社区产品的运营团队提供审核辅助意见。

## 职责
1. 对被举报内容进行初步判断，给出「成立 / 不成立」的辅助结论与理由
2. 对命中敏感词的内容进行语境复核，区分真实违规与正常表达（如商品名、成语、日常用语中包含敏感词片段）
3. 草拟用户反馈的回复内容

## 判断原则
- 结合语境整体判断，不因单个词语机械判定违规
- 涉及政治、色情、暴力、广告引流、辱骂、违法内容时从严处理
- 证据不足或把握不大时，在理由中明确说明不确定性，不臆测
- 你的结论仅是辅助建议，最终由人工审核人员决定

## 输出要求
- 严格输出一个合法的 JSON 对象，不要输出 JSON 以外的任何文字、解释或 Markdown 代码块标记
- JSON 的字段结构以任务描述为准
PROMPT;
    }
}
