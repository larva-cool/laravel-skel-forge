<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 分群规则操作符枚举
 *
 * 用于 SegmentRuleEvaluator 编译规则 JSON 时按操作符分发到对应 Builder 闭包。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum SegmentRuleOperator: string implements \JsonSerializable
{
    use HasLabel;

    case EQ = '=';             // 等于
    case NEQ = '!=';           // 不等于
    case GT = '>';             // 大于
    case GTE = '>=';           // 大于等于
    case LT = '<';             // 小于
    case LTE = '<=';           // 小于等于
    case BETWEEN = 'between';  // 区间
    case IN = 'in';            // 包含于
    case NOT_IN = 'not_in';    // 不包含于
    case IS_NULL = 'is_null';  // 为空
    case IS_NOT_NULL = 'is_not_null'; // 不为空

    /**
     * 获取操作符中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::EQ => '等于',
            self::NEQ => '不等于',
            self::GT => '大于',
            self::GTE => '大于等于',
            self::LT => '小于',
            self::LTE => '小于等于',
            self::BETWEEN => '区间',
            self::IN => '包含于',
            self::NOT_IN => '不包含于',
            self::IS_NULL => '为空',
            self::IS_NOT_NULL => '不为空',
        };
    }

    /**
     * 是否为 Null 判定类（value 不需要）
     */
    public function isNullCheck(): bool
    {
        return match ($this) {
            self::IS_NULL, self::IS_NOT_NULL => true,
            default => false,
        };
    }

    /**
     * 是否需要多值（between / in / not_in）
     */
    public function requiresMultipleValues(): bool
    {
        return match ($this) {
            self::BETWEEN, self::IN, self::NOT_IN => true,
            default => false,
        };
    }
}
