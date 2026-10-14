<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Models\System\SensitiveWord;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 预置一批基础敏感词（示例数据，运营可通过后台继续维护）
     *
     * @var array<int, array{word: string, category: string, level: string}>
     */
    private array $defaultWords = [
        // 涉政 / 违法（BLOCK 直接拦截）
        ['word' => '法轮功', 'category' => SensitiveCategory::POLITICAL, 'level' => SensitiveLevel::BLOCK],
        ['word' => '台独', 'category' => SensitiveCategory::POLITICAL, 'level' => SensitiveLevel::BLOCK],
        ['word' => '港独', 'category' => SensitiveCategory::POLITICAL, 'level' => SensitiveLevel::BLOCK],
        ['word' => '疆独', 'category' => SensitiveCategory::POLITICAL, 'level' => SensitiveLevel::BLOCK],
        ['word' => '藏独', 'category' => SensitiveCategory::POLITICAL, 'level' => SensitiveLevel::BLOCK],

        // 辱骂（REVIEW 待审核）
        ['word' => '傻逼', 'category' => SensitiveCategory::INSULT, 'level' => SensitiveLevel::REVIEW],
        ['word' => '傻B', 'category' => SensitiveCategory::INSULT, 'level' => SensitiveLevel::REVIEW],
        ['word' => 'sb', 'category' => SensitiveCategory::INSULT, 'level' => SensitiveLevel::REVIEW],
        ['word' => '操你妈', 'category' => SensitiveCategory::INSULT, 'level' => SensitiveLevel::REVIEW],
        ['word' => '草泥马', 'category' => SensitiveCategory::INSULT, 'level' => SensitiveLevel::REVIEW],
        ['word' => '王八蛋', 'category' => SensitiveCategory::INSULT, 'level' => SensitiveLevel::REVIEW],
        ['word' => '狗娘养', 'category' => SensitiveCategory::INSULT, 'level' => SensitiveLevel::REVIEW],
        ['word' => '去死', 'category' => SensitiveCategory::VIOLENCE, 'level' => SensitiveLevel::REVIEW],

        // 广告（REPLACE 自动替换）
        ['word' => '加微信', 'category' => SensitiveCategory::AD, 'level' => SensitiveLevel::REPLACE],
        ['word' => '加V', 'category' => SensitiveCategory::AD, 'level' => SensitiveLevel::REPLACE],
        ['word' => '加QQ', 'category' => SensitiveCategory::AD, 'level' => SensitiveLevel::REPLACE],
        ['word' => '兼职', 'category' => SensitiveCategory::AD, 'level' => SensitiveLevel::REPLACE],
        ['word' => '日赚', 'category' => SensitiveCategory::AD, 'level' => SensitiveLevel::REPLACE],
        ['word' => 'VX', 'category' => SensitiveCategory::AD, 'level' => SensitiveLevel::REPLACE],
        ['word' => '威信', 'category' => SensitiveCategory::AD, 'level' => SensitiveLevel::REPLACE],
    ];

    public function up(): void
    {
        $now = now();
        $inserts = [];
        foreach ($this->defaultWords as $item) {
            // 幂等：已存在则跳过
            if (SensitiveWord::withTrashed()->where('word', $item['word'])->exists()) {
                continue;
            }
            $inserts[] = array_merge($item, [
                'replacement' => '*',
                'hit_count' => 0,
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($inserts !== []) {
            SensitiveWord::insert($inserts);
        }
    }

    public function down(): void
    {
        SensitiveWord::whereIn('word', array_column($this->defaultWords, 'word'))->delete();
    }
};
