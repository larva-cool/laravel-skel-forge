<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Contracts;

use App\Enums\SensitiveLevel;

/**
 * 内容审核结果（本地 DFA 与云审核统一返回结构）
 */
class ModerationResult
{
    /**
     * @param  bool  $passed  是否通过（未命中 block 级别即通过）
     * @param  SensitiveLevel  $maxLevel  命中的最高级别
     * @param  array<int, array{word: string, level: SensitiveLevel, category: string}>  $hits  命中的敏感词列表
     * @param  string  $filtered  替换后的文本（replace 级别生效）
     * @param  string  $raw  原始文本
     */
    public function __construct(
        public readonly bool $passed,
        public readonly SensitiveLevel $maxLevel,
        public readonly array $hits,
        public readonly string $filtered,
        public readonly string $raw,
    ) {}

    public static function clean(string $text): self
    {
        return new self(true, SensitiveLevel::REPLACE, [], $text, $text);
    }

    /**
     * 是否需要进入人工审核队列
     */
    public function needsReview(): bool
    {
        return ! $this->passed || $this->maxLevel === SensitiveLevel::REVIEW;
    }

    /**
     * 是否被直接拦截
     */
    public function isBlocked(): bool
    {
        return $this->maxLevel === SensitiveLevel::BLOCK;
    }

    /**
     * 获取命中的敏感词字符串（用于错误提示）
     */
    public function hitWords(): array
    {
        return array_unique(array_column($this->hits, 'word'));
    }
}
