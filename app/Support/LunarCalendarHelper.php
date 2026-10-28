<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Overtrue\ChineseCalendar\Calendar;

/**
 * 农历助手类 - 公历/农历日期转换
 *
 * 封装 overtrue/chinese-calendar，仅暴露业务所需的月日级转换能力。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class LunarCalendarHelper
{
    /**
     * 将公历日期转换为农历月日
     *
     * @param  Carbon  $date  公历日期
     * @return array{month: int, day: int, is_leap: bool} 农历月、日、是否闰月
     */
    public static function solarToLunar(Carbon $date): array
    {
        $result = (new Calendar)->solar2lunar(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j'),
        );

        return [
            'month' => (int) $result['lunar_month'],
            'day' => (int) $result['lunar_day'],
            'is_leap' => (bool) ($result['is_leap'] ?? false),
        ];
    }

    /**
     * 判断指定公历日期是否命中某个农历月日（忽略闰月，仅匹配平月）
     */
    public static function isLunarDate(Carbon $date, int $lunarMonth, int $lunarDay): bool
    {
        $lunar = self::solarToLunar($date);

        return ! $lunar['is_leap']
            && $lunar['month'] === $lunarMonth
            && $lunar['day'] === $lunarDay;
    }
}
