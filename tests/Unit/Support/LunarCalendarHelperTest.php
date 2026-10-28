<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LunarCalendarHelper;
use Illuminate\Support\Carbon;
use Overtrue\ChineseCalendar\Calendar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * LunarCalendarHelper 单元测试
 */
#[CoversClass(LunarCalendarHelper::class)]
#[Group('automation')]
class LunarCalendarHelperTest extends TestCase
{
    #[Test]
    #[TestDox('solarToLunar 正确转换公历为农历月日')]
    public function solar_to_lunar_converts_correctly(): void
    {
        // 动态推算 2026 年中秋（农历八月十五）的公历日期再回转校验
        $solar = (new Calendar)->lunar2solar(2026, 8, 15);
        $date = Carbon::parse("{$solar['solar_year']}-{$solar['solar_month']}-{$solar['solar_day']}");

        $lunar = LunarCalendarHelper::solarToLunar($date);

        $this->assertSame(8, $lunar['month']);
        $this->assertSame(15, $lunar['day']);
        $this->assertFalse($lunar['is_leap']);
    }

    #[Test]
    #[TestDox('solarToLunar 转换春节：农历正月初一')]
    public function solar_to_lunar_spring_festival(): void
    {
        $solar = (new Calendar)->lunar2solar(2026, 1, 1);
        $date = Carbon::parse("{$solar['solar_year']}-{$solar['solar_month']}-{$solar['solar_day']}");

        $lunar = LunarCalendarHelper::solarToLunar($date);

        $this->assertSame(1, $lunar['month']);
        $this->assertSame(1, $lunar['day']);
    }

    #[Test]
    #[TestDox('isLunarDate 命中返回 true，未命中返回 false')]
    public function is_lunar_date_matches(): void
    {
        $solar = (new Calendar)->lunar2solar(2026, 5, 5);
        $date = Carbon::parse("{$solar['solar_year']}-{$solar['solar_month']}-{$solar['solar_day']}");

        $this->assertTrue(LunarCalendarHelper::isLunarDate($date, 5, 5));
        $this->assertFalse(LunarCalendarHelper::isLunarDate($date, 5, 6));
        $this->assertFalse(LunarCalendarHelper::isLunarDate($date->copy()->subDay(), 5, 5));
    }
}
