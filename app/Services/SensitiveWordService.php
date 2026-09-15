<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Contracts\ContentModerator;
use App\Contracts\ModerationResult;
use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Models\System\SensitiveWord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 敏感词过滤服务 —— 基于 DFA（确定性有限自动机）算法
 *
 * 工作流程：
 *  1. 从数据库加载启用状态的敏感词，构建 Trie 字典树（DFA）
 *  2. 字典树缓存至 Cache 层（避免每次请求查库）
 *  3. 检测文本时逐字符状态转移，识别命中词及最高级别
 *  4. 根据级别执行：replace（替换）/ review（标记待审）/ block（拦截）
 *  5. 如配置了云审核驱动，本地检测通过后再送云端二次复核
 */
class SensitiveWordService
{
    /** @var array<string, mixed>|null DFA 字典树 */
    protected ?array $dict = null;

    /** @var array<string, true> 干扰字符集合 */
    protected array $stopChars = [];

    protected bool $ignoreCase;

    protected bool $ignoreStopwords;

    protected string $cacheKey;

    protected int $cacheTtl;

    protected string $maskChar;

    public function __construct()
    {
        $this->ignoreCase = (bool) config('sensitive.local.ignore_case', true);
        $this->ignoreStopwords = (bool) config('sensitive.local.ignore_stopwords', true);
        $this->cacheKey = (string) config('sensitive.local.cache_key', 'sensitive_word:dfa_dict');
        $this->cacheTtl = (int) config('sensitive.local.cache_ttl', 1440);
        $this->maskChar = (string) config('sensitive.mask_char', '*');
        $this->stopChars = array_fill_keys(
            (array) config('sensitive.local.stop_chars', [' ', '*', '.', '-', '_', '#', '@']),
            true
        );
    }

    /**
     * 检测文本，返回审核结果
     */
    public function detect(string $text): ModerationResult
    {
        if ($text === '') {
            return ModerationResult::clean('');
        }

        $hits = $this->findHits($text);
        if ($hits === []) {
            // 本地未命中，若配置了云审核则送云端二次复核
            return $this->cloudModerate($text);
        }

        $maxLevel = $this->maxLevel($hits);
        $filtered = $this->applyReplace($text, $hits);
        $result = new ModerationResult(
            passed: $maxLevel !== SensitiveLevel::BLOCK,
            maxLevel: $maxLevel,
            hits: $hits,
            filtered: $filtered,
            raw: $text,
        );

        // 若未被 block 且配置了云审核，再送云端
        if ($result->passed && $this->shouldCloudModerate()) {
            return $this->mergeCloudResult($text, $result);
        }

        return $result;
    }

    /**
     * 检测并返回替换后的文本（便捷方法）
     *
     * @return array{filtered: string, blocked: bool, hits: array}
     */
    public function filter(string $text): array
    {
        $result = $this->detect($text);

        return [
            'filtered' => $result->filtered,
            'blocked' => $result->isBlocked(),
            'hits' => $result->hits,
        ];
    }

    /**
     * 刷新 DFA 字典缓存
     */
    public function refreshDict(): void
    {
        Cache::forget($this->cacheKey);
        $this->dict = null;
        $this->buildDict();
    }

    /**
     * 批量累加命中次数（异步调用）
     *
     * @param  array<string>  $words
     */
    public function incrementHitCount(array $words): void
    {
        if ($words === []) {
            return;
        }
        SensitiveWord::whereIn('word', $words)->increment('hit_count');
    }

    // -------------------------------------------------------------------------
    // DFA 字典树构建
    // -------------------------------------------------------------------------

    protected function getDict(): array
    {
        if ($this->dict !== null) {
            return $this->dict;
        }

        return $this->dict = $this->loadDict();
    }

    protected function loadDict(): array
    {
        $cached = Cache::get($this->cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $dict = $this->buildDict();

        if ($this->cacheTtl > 0) {
            Cache::put($this->cacheKey, $dict, now()->addMinutes($this->cacheTtl));
        } else {
            Cache::forever($this->cacheKey, $dict);
        }

        return $dict;
    }

    protected function buildDict(): array
    {
        $dict = [];
        $words = SensitiveWord::enabled()->select(['word', 'category', 'level', 'replacement'])->get();

        foreach ($words as $wordModel) {
            $word = trim((string) $wordModel->word);
            if ($word === '') {
                continue;
            }
            if ($this->ignoreCase) {
                $word = mb_strtolower($word, 'UTF-8');
            }

            $node = &$dict;
            $len = mb_strlen($word, 'UTF-8');
            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($word, $i, 1, 'UTF-8');
                if (! isset($node[$char])) {
                    $node[$char] = [];
                }
                $node = &$node[$char];
            }
            // 词结束标记
            $node['_end'] = true;
            $node['_word'] = $wordModel->word;
            $node['_level'] = $wordModel->level;
            $node['_category'] = $wordModel->category;
            $node['_replacement'] = $wordModel->replacement ?: $this->maskChar;
            unset($node);
        }

        return $dict;
    }

    // -------------------------------------------------------------------------
    // 文本匹配
    // -------------------------------------------------------------------------

    /**
     * 扫描文本，返回所有命中的敏感词（含位置、级别、分类）
     *
     * @return array<int, array{word: string, level: SensitiveLevel, category: SensitiveCategory, start: int, end: int, replacement: string}>
     */
    protected function findHits(string $text): array
    {
        $dict = $this->getDict();
        if ($dict === []) {
            return [];
        }

        $hits = [];
        $textLen = mb_strlen($text, 'UTF-8');

        $i = 0;
        while ($i < $textLen) {
            $node = $dict;
            $matchLen = 0;
            $lastMatch = null;

            for ($j = $i; $j < $textLen; $j++) {
                $char = mb_substr($text, $j, 1, 'UTF-8');
                $checkChar = $this->ignoreCase ? mb_strtolower($char, 'UTF-8') : $char;

                // 干扰字符处理：若该字符是当前 Trie 节点的合法子节点（即敏感词本身包含该字符），
                // 则沿 Trie 下钻以支持更长匹配；否则视为干扰插入符，跳过（保持 matchLen 不变）。
                // 仅在已开始匹配（matchLen > 0）时才允许跳过，避免开头的非敏感字符被盲目吞掉。
                if ($this->ignoreStopwords && isset($this->stopChars[$char])) {
                    if ($matchLen > 0 && ! isset($node[$checkChar])) {
                        continue;
                    }
                    // 若 $node[$checkChar] 存在（字符本身是词的一部分，如词中含 _），走正常下钻逻辑
                    if ($matchLen === 0 && ! isset($node[$checkChar])) {
                        // 起始位置的干扰字符直接跳出内层循环
                        break;
                    }
                }

                if (! isset($node[$checkChar])) {
                    break;
                }

                $node = $node[$checkChar];
                $matchLen++;

                if (! empty($node['_end'])) {
                    $lastMatch = [
                        'word' => $node['_word'],
                        'level' => $node['_level'],
                        'category' => $node['_category'],
                        'replacement' => $node['_replacement'],
                        'start' => $i,
                        'end' => $j,
                    ];
                    // 不立即 break，继续贪婪匹配更长的词（如"傻子"优先于"傻"）
                }
            }

            if ($lastMatch !== null) {
                $hits[] = $lastMatch;
                $i = $lastMatch['end'] + 1; // 跳过已匹配部分
            } else {
                $i++;
            }
        }

        return $hits;
    }

    // -------------------------------------------------------------------------
    // 替换与级别处理
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array{word: string, level: SensitiveLevel, category: SensitiveCategory, start: int, end: int, replacement: string}>  $hits
     */
    protected function maxLevel(array $hits): SensitiveLevel
    {
        $max = SensitiveLevel::REPLACE;
        foreach ($hits as $hit) {
            if ($hit['level'] === SensitiveLevel::BLOCK) {
                return SensitiveLevel::BLOCK;
            }
            if ($hit['level'] === SensitiveLevel::REVIEW && $max !== SensitiveLevel::BLOCK) {
                $max = SensitiveLevel::REVIEW;
            }
        }

        return $max;
    }

    /**
     * 对 replace 级别的命中词进行替换；review/block 保留原文
     *
     * @param  array<int, array{word: string, level: SensitiveLevel, start: int, end: int, replacement: string}>  $hits
     */
    protected function applyReplace(string $text, array $hits): string
    {
        // 从后向前替换，避免偏移问题
        $sorted = $hits;
        usort($sorted, fn ($a, $b) => $b['start'] <=> $a['start']);

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($sorted as $hit) {
            if ($hit['level'] !== SensitiveLevel::REPLACE) {
                continue;
            }
            $mask = $hit['replacement'] ?: $this->maskChar;
            // 计算匹配长度（考虑被跳过的干扰字符）
            for ($pos = $hit['start']; $pos <= $hit['end']; $pos++) {
                $ch = $chars[$pos] ?? '';
                if ($this->ignoreStopwords && isset($this->stopChars[$ch])) {
                    continue; // 干扰字符保留原样
                }
                $chars[$pos] = $mask;
            }
        }

        return implode('', $chars);
    }

    // -------------------------------------------------------------------------
    // 云审核扩展
    // -------------------------------------------------------------------------

    protected function shouldCloudModerate(): bool
    {
        return config('sensitive.default') === 'cloud'
            && app()->bound(ContentModerator::class);
    }

    protected function cloudModerate(string $text): ModerationResult
    {
        if (! $this->shouldCloudModerate()) {
            return ModerationResult::clean($text);
        }

        try {
            return app(ContentModerator::class)->moderate($text);
        } catch (\Throwable $e) {
            Log::warning('云内容审核服务异常，降级为本地结果', ['message' => $e->getMessage()]);
            if (config('sensitive.cloud.fallback_to_local', true)) {
                return ModerationResult::clean($text);
            }

            throw $e;
        }
    }

    /**
     * 本地结果 + 云结果合并（取最高级别）
     */
    protected function mergeCloudResult(string $text, ModerationResult $local): ModerationResult
    {
        try {
            $cloud = app(ContentModerator::class)->moderate($local->filtered);
            $maxLevel = $this->higherLevel($local->maxLevel, $cloud->maxLevel);
            $hits = array_merge($local->hits, $cloud->hits);

            return new ModerationResult(
                passed: $maxLevel !== SensitiveLevel::BLOCK,
                maxLevel: $maxLevel,
                hits: $hits,
                filtered: $cloud->filtered !== $text ? $cloud->filtered : $local->filtered,
                raw: $text,
            );
        } catch (\Throwable $e) {
            Log::warning('云内容审核服务异常，使用本地结果', ['message' => $e->getMessage()]);

            return $local;
        }
    }

    protected function higherLevel(SensitiveLevel $a, SensitiveLevel $b): SensitiveLevel
    {
        $order = [
            SensitiveLevel::REPLACE->value => 0,
            SensitiveLevel::REVIEW->value => 1,
            SensitiveLevel::BLOCK->value => 2,
        ];

        return ($order[$a->value] ?? 0) >= ($order[$b->value] ?? 0) ? $a : $b;
    }
}
