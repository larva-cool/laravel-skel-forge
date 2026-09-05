<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\IDCardHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * IDCardHelper 单元测试
 */
#[CoversClass(IDCardHelper::class)]
#[Group('support')]
#[Group('idcard')]
class IDCardHelperTest extends TestCase
{
    /**
     * 真实可校验的身份证样本（北京朝阳，1990-03-07，男，校验位 8）
     */
    private const SAMPLE_IDCARD = '110105199003078238';

    /**
     * 用于覆盖 generate / parse 联动的稳定参数。
     */
    private const SAMPLE_AREA = '110105'; // 北京市朝阳区
    private const SAMPLE_BIRTHDAY = '1990-03-07';

    #[Test]
    #[TestDox('validate 对合法身份证号返回 true')]
    public function validate_accepts_valid_id_card(): void
    {
        $this->assertTrue(IDCardHelper::validate(self::SAMPLE_IDCARD));
    }

    #[Test]
    #[TestDox('validate 接受数字类型身份证号')]
    public function validate_accepts_integer_id_card(): void
    {
        $this->assertTrue(IDCardHelper::validate((string) self::SAMPLE_IDCARD));
    }

    #[Test]
    #[TestDox('validate 对长度不足的身份证号返回 false')]
    public function validate_rejects_short_id_card(): void
    {
        $this->assertFalse(IDCardHelper::validate('11010519900307'));
    }

    #[Test]
    #[TestDox('validate 对长度过长的身份证号返回 false')]
    public function validate_rejects_long_id_card(): void
    {
        $this->assertFalse(IDCardHelper::validate('1101051990030782381'));
    }

    #[Test]
    #[TestDox('validate 对前 17 位非纯数字的身份证号返回 false')]
    public function validate_rejects_non_numeric_first_17_digits(): void
    {
        // 第 7 位（年份第一位）替换为字母
        $this->assertFalse(IDCardHelper::validate('110105A99003078238'));
    }

    #[Test]
    #[TestDox('validate 对错误校验位的身份证号返回 false')]
    public function validate_rejects_wrong_check_code(): void
    {
        // 校验位错误（应为 8，写成 1）
        $this->assertFalse(IDCardHelper::validate('110105199003078231'));
    }

    #[Test]
    #[TestDox('validate 校验位为小写 x 时合法')]
    public function validate_accepts_lowercase_x_check_code(): void
    {
        // 用 helper 自身计算校验位：尝试一组样本，找出 mod==2 的
        $first17 = null;
        for ($i = 0; $i < 1000; $i++) {
            $candidate = '11010519900307'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $power = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
            $sum = 0;
            for ($j = 0; $j < 17; $j++) {
                $sum += (int) $candidate[$j] * $power[$j];
            }
            if ($sum % 11 === 2) {
                $first17 = $candidate;
                break;
            }
        }
        $this->assertNotNull($first17, '应找到 mod==2 的 17 位样本');
        $this->assertTrue(IDCardHelper::validate($first17.'x'));
    }

    #[Test]
    #[TestDox('parse 对合法身份证号返回完整信息数组')]
    public function parse_returns_complete_info_for_valid_id_card(): void
    {
        $info = IDCardHelper::parse(self::SAMPLE_IDCARD);

        $this->assertIsArray($info);
        $this->assertSame(1990, (int) substr(self::SAMPLE_IDCARD, 6, 4));
        $this->assertArrayHasKey('age', $info);
        $this->assertArrayHasKey('birthday', $info);
        $this->assertArrayHasKey('gender', $info);
        $this->assertArrayHasKey('province_code', $info);
        $this->assertArrayHasKey('city_code', $info);
        $this->assertArrayHasKey('district_code', $info);
        $this->assertArrayHasKey('province', $info);
        $this->assertArrayHasKey('city', $info);
        $this->assertArrayHasKey('district', $info);
    }

    #[Test]
    #[TestDox('parse 正确提取生日字段')]
    public function parse_extracts_birthday_correctly(): void
    {
        $info = IDCardHelper::parse(self::SAMPLE_IDCARD);

        $this->assertSame('1990-03-07', $info['birthday']);
    }

    #[Test]
    #[TestDox('parse 正确提取年龄段（基于当前年份）')]
    public function parse_extracts_age_based_on_current_year(): void
    {
        $info = IDCardHelper::parse(self::SAMPLE_IDCARD);

        $this->assertSame((int) date('Y') - 1990, $info['age']);
    }

    #[Test]
    #[TestDox('parse 正确提取性别（第 17 位为奇数时为男）')]
    public function parse_extracts_gender_male_when_17th_digit_is_odd(): void
    {
        // 110105199003078238：第 17 位是 3（奇数） → 男
        $info = IDCardHelper::parse(self::SAMPLE_IDCARD);

        $this->assertSame('M', $info['gender']);
    }

    #[Test]
    #[TestDox('parse 正确提取性别（第 17 位为偶数时为女）')]
    public function parse_extracts_gender_female_when_17th_digit_is_even(): void
    {
        // 构造一个第 17 位为偶数的合法身份证号
        $idCard = IDCardHelper::generate(self::SAMPLE_AREA, 'F', self::SAMPLE_BIRTHDAY);

        $this->assertTrue(IDCardHelper::validate($idCard));

        $info = IDCardHelper::parse($idCard);

        $this->assertSame('F', $info['gender']);
    }

    #[Test]
    #[TestDox('parse 正确提取省市区代码及名称')]
    public function parse_extracts_region_codes_and_names(): void
    {
        $info = IDCardHelper::parse(self::SAMPLE_IDCARD);

        $this->assertSame('110000', $info['province_code']);
        $this->assertSame('110100', $info['city_code']);
        $this->assertSame('110105', $info['district_code']);
        $this->assertSame('北京市', $info['province']);
        $this->assertSame('市辖区', $info['city']);
        $this->assertSame('朝阳区', $info['district']);
    }

    #[Test]
    #[TestDox('parse 对非法身份证号返回 null')]
    public function parse_returns_null_for_invalid_id_card(): void
    {
        $this->assertNull(IDCardHelper::parse('not-a-valid-id-card'));
    }

    #[Test]
    #[TestDox('parse 对未知行政区划代码返回空字符串而非抛错')]
    public function parse_returns_empty_string_for_unknown_region(): void
    {
        $idCard = self::buildValidIdCardForRegion('999999', 1990, 3, 7);

        $info = IDCardHelper::parse($idCard);

        $this->assertIsArray($info);
        $this->assertSame('', $info['province']);
        $this->assertSame('', $info['city']);
        $this->assertSame('', $info['district']);
    }

    /**
     * 构造一个前 17 位 + 合法校验位的身份证号（用于测试未知区划场景）。
     * 等价于私有 getPowerSum / getCheckCode，但通过公共 generate 接口实现：
     * 先用合法区划生成有效号，再把前 6 位替换为未知区划并重算校验位。
     */
    private static function buildValidIdCardForRegion(string $region, int $year, int $month, int $day): string
    {
        $birthday = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $valid = IDCardHelper::generate('110105', 'M', $birthday); // 17 位 + 校验位
        $first17 = substr($valid, 0, 17);
        // 替换前 6 位为未知区划，保留生日 + 顺序位，返回小写 x
        $newFirst17 = $region.substr($first17, 6);

        return $newFirst17.self::computeCheckCode($newFirst17);
    }

    /**
     * 根据 GB 11643-1999 规则计算校验位（与 IDCardHelper::getCheckCode 等价）。
     *
     * @param  string  $first17  前 17 位数字
     */
    private static function computeCheckCode(string $first17): string
    {
        $power = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += (int) $first17[$i] * $power[$i];
        }

        return match ($sum % 11) {
            10 => '2',
            9 => 'x',
            8 => '4',
            7 => '5',
            6 => '6',
            5 => '7',
            4 => '8',
            3 => '9',
            2 => 'X',
            1 => '0',
            0 => '1',
            default => '',
        };
    }

    #[Test]
    #[TestDox('parse 替代 getInfo：对合法身份证返回数组字段，对非法返回 null')]
    public function parse_replaces_get_info(): void
    {
        $info = IDCardHelper::parse(self::SAMPLE_IDCARD);
        $this->assertIsArray($info);
        $this->assertNull(IDCardHelper::parse('invalid'));
    }

    #[Test]
    #[TestDox('generate 生成男（M）身份证号并通过 validate 校验')]
    public function generate_creates_valid_male_id_card(): void
    {
        $idCard = IDCardHelper::generate(self::SAMPLE_AREA, 'M', self::SAMPLE_BIRTHDAY);

        $this->assertTrue(IDCardHelper::validate($idCard));
        $this->assertSame('M', IDCardHelper::getGenderByIdCard($idCard));
    }

    #[Test]
    #[TestDox('generate 生成女（F）身份证号并通过 validate 校验')]
    public function generate_creates_valid_female_id_card(): void
    {
        $idCard = IDCardHelper::generate(self::SAMPLE_AREA, 'F', self::SAMPLE_BIRTHDAY);

        $this->assertTrue(IDCardHelper::validate($idCard));
        $this->assertSame('F', IDCardHelper::getGenderByIdCard($idCard));
    }

    #[Test]
    #[TestDox('generate 支持传入整型行政区划代码')]
    public function generate_accepts_integer_area_code(): void
    {
        $idCard = IDCardHelper::generate((int) self::SAMPLE_AREA, 'M', self::SAMPLE_BIRTHDAY);

        $this->assertTrue(IDCardHelper::validate($idCard));
        $this->assertStringStartsWith(self::SAMPLE_AREA, $idCard);
    }

    #[Test]
    #[TestDox('generate 自动去除生日参数中的连字符')]
    public function generate_strips_dashes_from_birthday(): void
    {
        $idCard = IDCardHelper::generate(self::SAMPLE_AREA, 'M', '1990-03-07');

        $this->assertTrue(IDCardHelper::validate($idCard));
        $this->assertSame('1990-03-07', IDCardHelper::getBirthdayByIdCard($idCard));
    }

    #[Test]
    #[TestDox('getAgeByIdCard 按当前年份计算年龄')]
    public function get_age_uses_current_year(): void
    {
        $this->assertSame((int) date('Y') - 1990, IDCardHelper::getAgeByIdCard(self::SAMPLE_IDCARD));
    }

    #[Test]
    #[TestDox('getBirthdayByIdCard 返回 YYYY-MM-DD 格式')]
    public function get_birthday_returns_iso_format(): void
    {
        $this->assertSame('1990-03-07', IDCardHelper::getBirthdayByIdCard(self::SAMPLE_IDCARD));
    }

    #[Test]
    #[TestDox('getGenderByIdCard 对整型入参同样生效')]
    public function get_gender_works_with_integer_input(): void
    {
        // 用 generate 生成纯数字校验位的身份证号（避免 X 强转 int 丢失末位）
        $idCard = IDCardHelper::generate('110105', 'F', '1990-03-07');
        $this->assertSame('F', IDCardHelper::getGenderByIdCard((int) $idCard));
    }

    #[Test]
    #[TestDox('getProvinceCodeByIdCard 返回 6 位省代码（前 2 位 + 0000）')]
    public function get_province_code_format(): void
    {
        $this->assertSame('110000', IDCardHelper::getProvinceCodeByIdCard(self::SAMPLE_IDCARD));
    }

    #[Test]
    #[TestDox('getCityCodeByIdCard 返回 6 位市代码（前 4 位 + 00）')]
    public function get_city_code_format(): void
    {
        $this->assertSame('110100', IDCardHelper::getCityCodeByIdCard(self::SAMPLE_IDCARD));
    }

    #[Test]
    #[TestDox('getDistrictCodeByIdCard 返回前 6 位区代码')]
    public function get_district_code_format(): void
    {
        $this->assertSame('110105', IDCardHelper::getDistrictCodeByIdCard(self::SAMPLE_IDCARD));
    }

    #[Test]
    #[TestDox('getProvinceByIdCard / getCityByIdCard / getDistrictByIdCard 返回对应名称')]
    public function get_region_names(): void
    {
        $this->assertSame('北京市', IDCardHelper::getProvinceByIdCard(self::SAMPLE_IDCARD));
        $this->assertSame('市辖区', IDCardHelper::getCityByIdCard(self::SAMPLE_IDCARD));
        $this->assertSame('朝阳区', IDCardHelper::getDistrictByIdCard(self::SAMPLE_IDCARD));
    }

    #[Test]
    #[TestDox('getLocationByIdCard 返回省/市/区三段地址')]
    public function get_location_returns_three_levels(): void
    {
        $location = IDCardHelper::getLocationByIdCard(self::SAMPLE_IDCARD);

        $this->assertSame(
            ['province' => '北京市', 'city' => '市辖区', 'district' => '朝阳区'],
            $location
        );
    }

    #[Test]
    #[DataProvider('birthdayProvider')]
    #[TestDox('getBirthdayByIdCard 对不同生日正确格式化')]
    public function get_birthday_handles_various_dates(string $idCard, string $expected): void
    {
        $this->assertSame($expected, IDCardHelper::getBirthdayByIdCard($idCard));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function birthdayProvider(): array
    {
        $cases = [];
        $samples = [
            '1990-01-01',
            '2000-12-31',
            '1985-07-15',
        ];
        foreach ($samples as $date) {
            $idCard = IDCardHelper::generate('110105', 'M', $date);
            $cases[$date] = [$idCard, $date];
        }

        return $cases;
    }
}
