<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Segment;

use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Models\User;
use App\Services\Segment\SegmentRuleEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SegmentRuleEvaluator 单元测试
 */
#[CoversClass(SegmentRuleEvaluator::class)]
#[Group('admin')]
#[Group('segment')]
class SegmentRuleEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private SegmentRuleEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new SegmentRuleEvaluator;
    }

    private function makeUser(array $overrides = []): User
    {
        $u = User::factory()->create(array_merge([
            'status' => 1,
            'available_points' => 0,
            'available_coins' => 0,
            'login_count' => 0,
            'vip_expires_at' => null,
            'last_login_at' => null,
        ], $overrides));

        // RefreshDatabase + 软删：默认不会软删
        return $u;
    }

    /**
     * @param  array<string,mixed>|null  $rule
     * @return array<int, int>
     */
    private function idsMatching(?array $rule): array
    {
        $apply = $this->evaluator->compile($rule);
        $q = User::query();
        $apply($q);

        return $q->pluck('id')->all();
    }

    #[Test]
    #[TestDox('空规则返回 identity 闭包（不过滤任何用户）')]
    public function empty_rule_returns_identity_closure(): void
    {
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();

        $this->assertSame([$u1->id, $u2->id], $this->idsMatching(null));
        $this->assertSame([$u1->id, $u2->id], $this->idsMatching([]));
    }

    #[Test]
    #[TestDox('AND 逻辑：status=1 与 available_points>=100 同时满足才命中')]
    public function and_logic_intersects_conditions(): void
    {
        $a = $this->makeUser(['status' => 1, 'available_points' => 200]);
        $b = $this->makeUser(['status' => 0, 'available_points' => 200]);
        $c = $this->makeUser(['status' => 1, 'available_points' => 50]);
        $this->makeUser(['status' => 0, 'available_points' => 50]);

        $rule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'status', 'op' => '=', 'value' => 1],
                ['field' => 'available_points', 'op' => '>=', 'value' => 100],
            ],
        ];

        $this->assertSame([$a->id], $this->idsMatching($rule));
    }

    #[Test]
    #[TestDox('OR 逻辑：任一条件满足即命中')]
    public function or_logic_unions_conditions(): void
    {
        $a = $this->makeUser(['status' => 1, 'available_points' => 0]);
        $b = $this->makeUser(['status' => 0, 'available_points' => 200]);
        $c = $this->makeUser(['status' => 0, 'available_points' => 0]);
        $d = $this->makeUser(['status' => 1, 'available_points' => 200]);

        $rule = [
            'version' => 1,
            'logic' => 'or',
            'conditions' => [
                ['field' => 'status', 'op' => '=', 'value' => 1],
                ['field' => 'available_points', 'op' => '>=', 'value' => 100],
            ],
        ];

        $matched = $this->idsMatching($rule);
        sort($matched);
        $expected = [$a->id, $b->id, $d->id];
        sort($expected);
        $this->assertSame($expected, $matched);
    }

    #[Test]
    #[TestDox('IS_NULL / IS_NOT_NULL 操作符')]
    public function null_check_operators(): void
    {
        $withVip = $this->makeUser(['vip_expires_at' => Carbon::now()->addDay()]);
        $withoutVip = $this->makeUser(['vip_expires_at' => null]);

        $nullRule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'vip_expires_at', 'op' => 'is_null'],
            ],
        ];
        $this->assertSame([$withoutVip->id], $this->idsMatching($nullRule));

        $notNullRule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'vip_expires_at', 'op' => 'is_not_null'],
            ],
        ];
        $this->assertSame([$withVip->id], $this->idsMatching($notNullRule));
    }

    #[Test]
    #[TestDox('BETWEEN 操作符：解析 now / today-1d 等特殊值')]
    public function between_resolves_special_values(): void
    {
        $now = Carbon::now();
        $a = $this->makeUser(['last_login_at' => $now->copy()->subDays(3)]);
        $b = $this->makeUser(['last_login_at' => $now->copy()->subDays(10)]);
        $this->makeUser(['last_login_at' => null]);

        $rule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'last_login_at', 'op' => 'between', 'value' => ['now-7d', 'now']],
            ],
        ];

        $this->assertSame([$a->id], $this->idsMatching($rule));
    }

    #[Test]
    #[TestDox('IN / NOT_IN 操作符')]
    public function in_and_not_in_operators(): void
    {
        $a = $this->makeUser(['available_points' => 100]);
        $b = $this->makeUser(['available_points' => 200]);
        $c = $this->makeUser(['available_points' => 300]);

        $inRule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'available_points', 'op' => 'in', 'value' => [100, 200]],
            ],
        ];
        $matched = $this->idsMatching($inRule);
        sort($matched);
        $this->assertSame([$a->id, $b->id], $matched);

        $notInRule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'available_points', 'op' => 'not_in', 'value' => [100]],
            ],
        ];
        $matched = $this->idsMatching($notInRule);
        sort($matched);
        $this->assertSame([$b->id, $c->id], $matched);
    }

    #[Test]
    #[TestDox('NEQ 操作符')]
    public function not_equal_operator(): void
    {
        $a = $this->makeUser(['available_points' => 0]);
        $b = $this->makeUser(['available_points' => 100]);
        $c = $this->makeUser(['available_points' => 200]);

        $rule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'available_points', 'op' => '!=', 'value' => 0],
            ],
        ];
        $matched = $this->idsMatching($rule);
        sort($matched);
        $this->assertSame([$b->id, $c->id], $matched);
    }

    #[Test]
    #[TestDox('GT / LT / GTE / LTE 比较操作符')]
    public function comparison_operators(): void
    {
        $a = $this->makeUser(['available_points' => 100]);
        $b = $this->makeUser(['available_points' => 200]);
        $c = $this->makeUser(['available_points' => 300]);

        $gt = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'available_points', 'op' => '>', 'value' => 200],
        ]];
        $this->assertSame([$c->id], $this->idsMatching($gt));

        $gte = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'available_points', 'op' => '>=', 'value' => 200],
        ]];
        $matched = $this->idsMatching($gte);
        sort($matched);
        $this->assertSame([$b->id, $c->id], $matched);

        $lt = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'available_points', 'op' => '<', 'value' => 200],
        ]];
        $this->assertSame([$a->id], $this->idsMatching($lt));

        $lte = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'available_points', 'op' => '<=', 'value' => 200],
        ]];
        $matched = $this->idsMatching($lte);
        sort($matched);
        $this->assertSame([$a->id, $b->id], $matched);
    }

    #[Test]
    #[TestDox('SegmentRuleField::resolveValue 解析 now/today/now-7d/today-1d/now-3h')]
    public function resolve_value_parses_special_strings(): void
    {
        $now = Carbon::now();

        $this->assertTrue($now->diffInSeconds(SegmentRuleField::VIP_EXPIRES_AT->resolveValue('now')) < 2);

        $today = SegmentRuleField::VIP_EXPIRES_AT->resolveValue('today');
        $this->assertTrue($today->isStartOfDay());

        $now7d = SegmentRuleField::VIP_EXPIRES_AT->resolveValue('now-7d');
        $this->assertEqualsWithDelta($now->copy()->subDays(7)->timestamp, $now7d->timestamp, 2);

        $now3h = SegmentRuleField::VIP_EXPIRES_AT->resolveValue('now-3h');
        $this->assertEqualsWithDelta($now->copy()->subHours(3)->timestamp, $now3h->timestamp, 2);

        $today1 = SegmentRuleField::VIP_EXPIRES_AT->resolveValue('today-1d');
        $this->assertTrue($today1->isStartOfDay());
        $this->assertSame($today->copy()->subDay()->toDateString(), $today1->toDateString());

        // 非特殊值原样返回
        $this->assertSame('2026-01-01', SegmentRuleField::VIP_EXPIRES_AT->resolveValue('2026-01-01'));
        $this->assertSame(100, SegmentRuleField::AVAILABLE_POINTS->resolveValue(100));

        // 数组逐项解析
        $resolved = SegmentRuleField::LAST_LOGIN_AT->resolveValue(['now-1d', 'now']);
        $this->assertIsArray($resolved);
        $this->assertCount(2, $resolved);
        $this->assertEqualsWithDelta($now->copy()->subDay()->timestamp, $resolved[0]->timestamp, 2);
    }

    #[Test]
    #[TestDox('非法字段抛出 InvalidArgumentException')]
    public function invalid_field_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->evaluator->validate([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'unknown_column', 'op' => '=', 'value' => 1],
            ],
        ]);
    }

    #[Test]
    #[TestDox('非法操作符抛出 InvalidArgumentException')]
    public function invalid_operator_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->evaluator->validate([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'status', 'op' => '~~', 'value' => 1],
            ],
        ]);
    }

    #[Test]
    #[TestDox('字段与操作符不兼容（status 不能用 >）抛出异常')]
    public function incompatible_field_operator_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->evaluator->validate([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'status', 'op' => '>', 'value' => 1],
            ],
        ]);
    }

    #[Test]
    #[TestDox('between 缺少 value 数组抛出异常')]
    public function between_requires_array_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->evaluator->validate([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'available_points', 'op' => 'between', 'value' => 10],
            ],
        ]);
    }

    #[Test]
    #[TestDox('不支持的 logic 抛出异常')]
    public function unsupported_logic_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->evaluator->validate([
            'version' => 1,
            'logic' => 'xor',
            'conditions' => [
                ['field' => 'status', 'op' => '=', 'value' => 1],
            ],
        ]);
    }

    #[Test]
    #[TestDox('规则缺少 conditions 数组抛出异常')]
    public function missing_conditions_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->evaluator->validate(['version' => 1, 'logic' => 'and']);
    }

    #[Test]
    #[TestDox('枚举基础属性：操作符分类')]
    public function operator_classification_helpers(): void
    {
        $this->assertTrue(SegmentRuleOperator::IS_NULL->isNullCheck());
        $this->assertTrue(SegmentRuleOperator::IS_NOT_NULL->isNullCheck());
        $this->assertFalse(SegmentRuleOperator::EQ->isNullCheck());

        $this->assertTrue(SegmentRuleOperator::BETWEEN->requiresMultipleValues());
        $this->assertTrue(SegmentRuleOperator::IN->requiresMultipleValues());
        $this->assertTrue(SegmentRuleOperator::NOT_IN->requiresMultipleValues());
        $this->assertFalse(SegmentRuleOperator::EQ->requiresMultipleValues());
    }

    #[Test]
    #[TestDox('枚举基础属性：字段的 valueType 与 label')]
    public function field_basics(): void
    {
        $this->assertSame('int', SegmentRuleField::AVAILABLE_POINTS->valueType());
        $this->assertSame('datetime', SegmentRuleField::LAST_LOGIN_AT->valueType());
        $this->assertSame('账号状态', SegmentRuleField::STATUS->label());
        $this->assertSame('可用积分', SegmentRuleField::AVAILABLE_POINTS->label());

        // allowedOperators 含期望
        $ops = SegmentRuleField::STATUS->allowedOperators();
        $this->assertContains(SegmentRuleOperator::EQ, $ops);
        $this->assertContains(SegmentRuleOperator::IN, $ops);
        $this->assertNotContains(SegmentRuleOperator::GT, $ops);
    }
}
