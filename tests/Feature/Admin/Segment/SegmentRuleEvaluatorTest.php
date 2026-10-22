<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Segment;

use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Enums\SocialProvider;
use App\Models\Invite\InviteRecord;
use App\Models\System\Social;
use App\Models\User;
use App\Models\Wallet\Recharge;
use App\Models\Wallet\Withdraw;
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

    #[Test]
    #[TestDox('gender / birthday 字段基础属性：relation 返回 profile')]
    public function profile_field_basics(): void
    {
        $this->assertSame('int', SegmentRuleField::GENDER->valueType());
        $this->assertSame('datetime', SegmentRuleField::BIRTHDAY->valueType());
        $this->assertSame('性别', SegmentRuleField::GENDER->label());
        $this->assertSame('生日', SegmentRuleField::BIRTHDAY->label());

        // relation：gender / birthday 位于 profile 关联
        $this->assertSame('profile', SegmentRuleField::GENDER->relation());
        $this->assertSame('profile', SegmentRuleField::BIRTHDAY->relation());

        // users 主表字段 relation 返回 null
        $this->assertNull(SegmentRuleField::STATUS->relation());
        $this->assertNull(SegmentRuleField::AVAILABLE_POINTS->relation());
    }

    #[Test]
    #[TestDox('gender 字段编译为 whereHas profile 闭包')]
    public function gender_field_compiles_to_where_has(): void
    {
        $male = $this->makeUser();
        $male->profile()->update(['gender' => 1]);

        $female = $this->makeUser();
        $female->profile()->update(['gender' => 2]);

        $unknown = $this->makeUser(); // 默认 gender=0

        $rule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'gender', 'op' => '=', 'value' => 1],
            ],
        ];

        $this->assertSame([$male->id], $this->idsMatching($rule));
    }

    #[Test]
    #[TestDox('birthday 字段编译为 whereHas profile 闭包')]
    public function birthday_field_compiles_to_where_has(): void
    {
        $young = $this->makeUser();
        $young->profile()->update(['birthday' => '2005-06-15']);

        $old = $this->makeUser();
        $old->profile()->update(['birthday' => '1990-01-01']);

        $noBirthday = $this->makeUser(); // birthday=null

        $rule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'birthday', 'op' => '>', 'value' => '2000-01-01'],
            ],
        ];

        $this->assertSame([$young->id], $this->idsMatching($rule));
    }

    #[Test]
    #[TestDox('gender 与 users 主表字段 AND 组合：跨表查询正确')]
    public function gender_combined_with_main_table_field(): void
    {
        $match = $this->makeUser();
        $match->profile()->update(['gender' => 2]);

        $wrongGender = $this->makeUser();
        $wrongGender->profile()->update(['gender' => 1]);

        $frozenFemale = $this->makeUser();
        $frozenFemale->profile()->update(['gender' => 2]);
        $frozenFemale->forceFill(['status' => 0])->saveQuietly();

        $rule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'status', 'op' => '=', 'value' => 1],
                ['field' => 'gender', 'op' => '=', 'value' => 2],
            ],
        ];

        $this->assertSame([$match->id], $this->idsMatching($rule));
    }

    #[Test]
    #[TestDox('余额字段：比较与区间')]
    public function balance_field_comparisons(): void
    {
        $rich = $this->makeUser(['balance' => 500]);
        $mid = $this->makeUser(['balance' => 100]);
        $poor = $this->makeUser(['balance' => 0]);
        $superRich = $this->makeUser(['balance' => 1000]);

        $gte = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'balance', 'op' => '>=', 'value' => 100],
        ]];
        $matched = $this->idsMatching($gte);
        sort($matched);
        $expected = [$rich->id, $mid->id, $superRich->id];
        sort($expected);
        $this->assertSame($expected, $matched);

        $between = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'frozen_balance', 'op' => 'between', 'value' => [1, 1000]],
        ]];
        $frozenRich = $this->makeUser(['frozen_balance' => 88]);
        $matched = $this->idsMatching($between);
        $this->assertSame([$frozenRich->id], $matched);
    }

    #[Test]
    #[TestDox('省市字段：profile 关联的 IN 与 IS_NULL')]
    public function province_and_city_fields(): void
    {
        $beijing = $this->makeUser();
        $beijing->profile()->update(['province_id' => 110000, 'city_id' => 110100]);

        $shanghai = $this->makeUser();
        $shanghai->profile()->update(['province_id' => 310000, 'city_id' => 310100]);

        $noArea = $this->makeUser();

        $inRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'province_id', 'op' => 'in', 'value' => [110000, 310000]],
        ]];
        $matched = $this->idsMatching($inRule);
        sort($matched);
        $this->assertSame([$beijing->id, $shanghai->id], $matched);

        $cityRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'city_id', 'op' => '=', 'value' => 310100],
        ]];
        $this->assertSame([$shanghai->id], $this->idsMatching($cityRule));

        $nullRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'province_id', 'op' => 'is_null'],
        ]];
        $this->assertSame([$noArea->id], $this->idsMatching($nullRule));
    }

    #[Test]
    #[TestDox('绑定状态字段：邮箱 / 手机号 / 用户名的 IS_NULL 与 IS_NOT_NULL')]
    public function contact_binding_null_checks(): void
    {
        $withEmail = $this->makeUser(['email' => 'a@example.com']);
        $withoutEmail = $this->makeUser(['email' => null]);

        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'email', 'op' => 'is_not_null'],
        ]];
        $this->assertSame([$withEmail->id], $this->idsMatching($rule));

        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'email', 'op' => 'is_null'],
        ]];
        $this->assertSame([$withoutEmail->id], $this->idsMatching($rule));

        $withPhone = $this->makeUser(['phone' => '+8613800138000']);
        $this->makeUser(['phone' => null]);
        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'phone', 'op' => 'is_not_null'],
        ]];
        $matched = $this->idsMatching($rule);
        $this->assertContains($withPhone->id, $matched);

        $withUsername = $this->makeUser(['username' => 'user_'.uniqid()]);
        $this->makeUser(['username' => null]);
        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'username', 'op' => 'is_not_null'],
        ]];
        $matched = $this->idsMatching($rule);
        $this->assertContains($withUsername->id, $matched);
    }

    #[Test]
    #[TestDox('邮箱验证字段：extra 关联的 IS_NOT_NULL')]
    public function email_verified_at_null_check(): void
    {
        $verified = $this->makeUser();
        $verified->extra()->update(['email_verified_at' => now()]);

        $unverified = $this->makeUser();

        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'email_verified_at', 'op' => 'is_not_null'],
        ]];
        $this->assertSame([$verified->id], $this->idsMatching($rule));

        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'email_verified_at', 'op' => 'is_null'],
        ]];
        $this->assertSame([$unverified->id], $this->idsMatching($rule));
    }

    #[Test]
    #[TestDox('社交平台绑定：已绑定微信 / 未绑定微信')]
    public function social_provider_existence(): void
    {
        $wechatUser = $this->makeUser();
        Social::query()->create([
            'user_id' => $wechatUser->id,
            'user_type' => $wechatUser->getMorphClass(),
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'openid_'.uniqid(),
        ]);

        $appleUser = $this->makeUser();
        Social::query()->create([
            'user_id' => $appleUser->id,
            'user_type' => $appleUser->getMorphClass(),
            'provider' => SocialProvider::APPLE,
            'openid' => 'openid_'.uniqid(),
        ]);

        $noSocial = $this->makeUser();

        $boundRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'social_provider', 'op' => '=', 'value' => SocialProvider::WECHAT_MP->value],
        ]];
        $this->assertSame([$wechatUser->id], $this->idsMatching($boundRule));

        $unboundRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'social_provider', 'op' => '!=', 'value' => SocialProvider::WECHAT_MP->value],
        ]];
        $matched = $this->idsMatching($unboundRule);
        sort($matched);
        $this->assertSame([$appleUser->id, $noSocial->id], $matched);
    }

    #[Test]
    #[TestDox('充值次数：仅统计已到账订单')]
    public function recharge_count_aggregate(): void
    {
        $whale = $this->makeUser();
        Recharge::factory()->forUser($whale)->success()->count(3)->create();
        Recharge::factory()->forUser($whale)->create(); // 待支付不计入

        $normal = $this->makeUser();
        Recharge::factory()->forUser($normal)->success()->create();

        $none = $this->makeUser();

        $gteRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'recharge_count', 'op' => '>=', 'value' => 2],
        ]];
        $this->assertSame([$whale->id], $this->idsMatching($gteRule));

        $zeroRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'recharge_count', 'op' => '=', 'value' => 0],
        ]];
        $this->assertSame([$none->id], $this->idsMatching($zeroRule));
    }

    #[Test]
    #[TestDox('累计充值金额：SUM 聚合比较与区间')]
    public function recharge_total_sum_aggregate(): void
    {
        $whale = $this->makeUser();
        Recharge::factory()->forUser($whale)->success()->create(['amount' => 600]);
        Recharge::factory()->forUser($whale)->success()->create(['amount' => 500]);
        Recharge::factory()->forUser($whale)->create(['amount' => 9999]); // 待支付不计入

        $normal = $this->makeUser();
        Recharge::factory()->forUser($normal)->success()->create(['amount' => 100]);

        $none = $this->makeUser();

        $gteRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'recharge_total', 'op' => '>=', 'value' => 1000],
        ]];
        $this->assertSame([$whale->id], $this->idsMatching($gteRule));

        $betweenRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'recharge_total', 'op' => 'between', 'value' => [50, 500]],
        ]];
        $this->assertSame([$normal->id], $this->idsMatching($betweenRule));

        // 无明细记录的用户不产生分组行，= 0 不命中
        $zeroRule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'recharge_total', 'op' => '=', 'value' => 0],
        ]];
        $this->assertSame([], $this->idsMatching($zeroRule));
    }

    #[Test]
    #[TestDox('提现次数：关系计数')]
    public function withdraw_count_aggregate(): void
    {
        $frequent = $this->makeUser();
        Withdraw::factory()->count(2)->create([
            'owner_type' => $frequent->getMorphClass(),
            'owner_id' => $frequent->id,
        ]);

        $none = $this->makeUser();

        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'withdraw_count', 'op' => '>', 'value' => 0],
        ]];
        $this->assertSame([$frequent->id], $this->idsMatching($rule));

        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'withdraw_count', 'op' => '=', 'value' => 0],
        ]];
        $this->assertSame([$none->id], $this->idsMatching($rule));
    }

    #[Test]
    #[TestDox('邀请人数：关系计数')]
    public function invite_count_aggregate(): void
    {
        $inviter = $this->makeUser();
        $inviteeA = $this->makeUser();
        $inviteeB = $this->makeUser();
        InviteRecord::factory()->forInviter($inviter)->forInvitee($inviteeA)->create();
        InviteRecord::factory()->forInviter($inviter)->forInvitee($inviteeB)->create();

        $none = $this->makeUser();

        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'invite_count', 'op' => '>=', 'value' => 2],
        ]];
        $this->assertSame([$inviter->id], $this->idsMatching($rule));

        $rule = ['version' => 1, 'logic' => 'and', 'conditions' => [
            ['field' => 'invite_count', 'op' => '<', 'value' => 1],
        ]];
        $matched = $this->idsMatching($rule);
        sort($matched);
        $expected = [$inviteeA->id, $inviteeB->id, $none->id];
        sort($expected);
        $this->assertSame($expected, $matched);
    }

    #[Test]
    #[TestDox('聚合/存在性字段与主表字段 OR 组合')]
    public function aggregate_combined_with_column_field_in_or_logic(): void
    {
        $vipUser = $this->makeUser(['vip_expires_at' => Carbon::now()->addDay()]);

        $payUser = $this->makeUser();
        Recharge::factory()->forUser($payUser)->success()->create(['amount' => 500]);

        $this->makeUser(); // 既不满足 VIP 也无充值

        $rule = ['version' => 1, 'logic' => 'or', 'conditions' => [
            ['field' => 'vip_expires_at', 'op' => '>', 'value' => 'now'],
            ['field' => 'recharge_total', 'op' => '>', 'value' => 100],
        ]];

        $matched = $this->idsMatching($rule);
        sort($matched);
        $this->assertSame([$vipUser->id, $payUser->id], $matched);
    }

    #[Test]
    #[TestDox('聚合字段操作符白名单：social_provider 不允许 in')]
    public function aggregate_field_operator_whitelist_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->evaluator->validate([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'social_provider', 'op' => 'in', 'value' => ['wechat_mp']],
            ],
        ]);
    }

    #[Test]
    #[TestDox('新字段基础属性：queryType / 关联 / 值类型')]
    public function new_field_basics(): void
    {
        $this->assertSame('number', SegmentRuleField::BALANCE->valueType());
        $this->assertSame('column', SegmentRuleField::BALANCE->queryType());

        $this->assertSame('exists', SegmentRuleField::SOCIAL_PROVIDER->queryType());
        $this->assertSame('socials', SegmentRuleField::SOCIAL_PROVIDER->aggregateRelation());
        $this->assertSame('provider', SegmentRuleField::SOCIAL_PROVIDER->column());

        $this->assertSame('count', SegmentRuleField::RECHARGE_COUNT->queryType());
        $this->assertSame('recharges', SegmentRuleField::RECHARGE_COUNT->aggregateRelation());
        $this->assertNotNull(SegmentRuleField::RECHARGE_COUNT->aggregateConstraint());

        $this->assertSame('sum', SegmentRuleField::RECHARGE_TOTAL->queryType());
        $this->assertSame('amount', SegmentRuleField::RECHARGE_TOTAL->column());
        $this->assertSame('owner_id', SegmentRuleField::RECHARGE_TOTAL->aggregateGroupColumn());

        $this->assertSame('invitees', SegmentRuleField::INVITE_COUNT->aggregateRelation());
        $this->assertSame('withdraws', SegmentRuleField::WITHDRAW_COUNT->aggregateRelation());

        $this->assertSame('profile', SegmentRuleField::PROVINCE_ID->relation());
        $this->assertSame('extra', SegmentRuleField::EMAIL_VERIFIED_AT->relation());

        // 充值类字段操作符不含 in / between
        $countOps = SegmentRuleField::RECHARGE_COUNT->allowedOperators();
        $this->assertContains(SegmentRuleOperator::GTE, $countOps);
        $this->assertNotContains(SegmentRuleOperator::IN, $countOps);
        $this->assertNotContains(SegmentRuleOperator::BETWEEN, $countOps);
    }
}
