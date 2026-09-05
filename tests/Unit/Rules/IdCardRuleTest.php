<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\IdCardRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * IdCardRule 单元测试
 */
#[CoversClass(IdCardRule::class)]
#[Group('rules')]
#[Group('idcard')]
class IdCardRuleTest extends TestCase
{
    /**
     * 真实可校验的身份证样本（北京朝阳，1990-03-07，男，校验位 8）
     */
    private const SAMPLE_IDCARD = '110105199003078238';

    #[Test]
    #[TestDox('通过 Validator 对合法身份证号返回 true')]
    public function validator_passes_for_valid_id_card(): void
    {
        $validator = Validator::make(
            ['id_card_no' => self::SAMPLE_IDCARD],
            ['id_card_no' => ['required', 'string', new IdCardRule]]
        );

        $this->assertFalse($validator->fails());
    }

    #[Test]
    #[TestDox('通过 Validator 对校验位错误的身份证号返回 false')]
    public function validator_fails_for_wrong_check_code(): void
    {
        $validator = Validator::make(
            ['id_card_no' => '110105199003078231'],
            ['id_card_no' => ['required', 'string', new IdCardRule]]
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id_card_no', $validator->errors()->toArray());
    }

    #[Test]
    #[TestDox('对非字符串类型（数组）触发验证失败')]
    public function fails_for_non_scalar_value(): void
    {
        $validator = Validator::make(
            ['id_card_no' => ['not-a-string']],
            ['id_card_no' => ['nullable', new IdCardRule]]
        );

        $this->assertTrue($validator->fails());
    }

    #[Test]
    #[TestDox('空值不触发 IdCardRule（交由 required/required_if 处理）')]
    public function empty_value_does_not_trigger_rule(): void
    {
        $validator = Validator::make(
            ['id_card_no' => null],
            ['id_card_no' => ['nullable', new IdCardRule]]
        );

        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['id_card_no' => ''],
            ['id_card_no' => ['nullable', new IdCardRule]]
        );

        $this->assertFalse($validator->fails());
    }

    #[Test]
    #[DataProvider('invalidIdCardProvider')]
    #[TestDox('多种非法身份证号均触发验证失败')]
    public function rejects_invalid_id_cards(string $idCard): void
    {
        $validator = Validator::make(
            ['id_card_no' => $idCard],
            ['id_card_no' => ['nullable', new IdCardRule]]
        );

        $this->assertTrue($validator->fails(), "应判定 {$idCard} 非法");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidIdCardProvider(): array
    {
        return [
            '长度不足' => ['11010519900307'],
            '长度过长' => ['1101051990030782381'],
            '前 17 位含字母' => ['110105A99003078238'],
            '校验位错误' => ['110105199003078231'],
            '全为字母' => ['abcdefghijklmnopqrs'],
        ];
    }
}
