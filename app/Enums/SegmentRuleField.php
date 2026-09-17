<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 分群规则可用字段枚举
 *
 * 列出规则条件允许引用的用户表字段，每个字段声明：
 *  - column：实际数据库列名
 *  - valueType：值类型（int/string/datetime）
 *  - allowedOperators：该字段允许的操作符白名单
 *  - resolveValue：把规则 JSON 中的"特殊值"（now / today / now-7d）解析为可比较的字面量
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum SegmentRuleField: string implements \JsonSerializable
{
    use HasLabel;

    case STATUS = 'status';
    case IDENTITY_STATUS = 'identity_status';
    case IDENTITY_TYPE = 'identity_type';
    case VIP_EXPIRES_AT = 'vip_expires_at';
    case LAST_LOGIN_AT = 'last_login_at';
    case LAST_ACTIVE_AT = 'last_active_at';
    case CREATED_AT = 'created_at';
    case AVAILABLE_POINTS = 'available_points';
    case AVAILABLE_COINS = 'available_coins';
    case LOGIN_COUNT = 'login_count';

    /**
     * 数据库列名
     */
    public function column(): string
    {
        return $this->value;
    }

    /**
     * 值类型：int / string / datetime
     */
    public function valueType(): string
    {
        return match ($this) {
            self::STATUS, self::AVAILABLE_POINTS, self::AVAILABLE_COINS, self::LOGIN_COUNT => 'int',
            self::IDENTITY_STATUS, self::IDENTITY_TYPE => 'string',
            self::VIP_EXPIRES_AT, self::LAST_LOGIN_AT, self::LAST_ACTIVE_AT, self::CREATED_AT => 'datetime',
        };
    }

    /**
     * 允许的操作符白名单
     *
     * @return array<int, SegmentRuleOperator>
     */
    public function allowedOperators(): array
    {
        return match ($this) {
            self::STATUS => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
                SegmentRuleOperator::IN,
                SegmentRuleOperator::NOT_IN,
            ],
            self::IDENTITY_STATUS => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
                SegmentRuleOperator::IN,
                SegmentRuleOperator::NOT_IN,
            ],
            self::IDENTITY_TYPE => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
                SegmentRuleOperator::IN,
                SegmentRuleOperator::NOT_IN,
                SegmentRuleOperator::IS_NULL,
                SegmentRuleOperator::IS_NOT_NULL,
            ],
            self::VIP_EXPIRES_AT => [
                SegmentRuleOperator::GT,
                SegmentRuleOperator::GTE,
                SegmentRuleOperator::LT,
                SegmentRuleOperator::LTE,
                SegmentRuleOperator::IS_NULL,
                SegmentRuleOperator::IS_NOT_NULL,
            ],
            self::AVAILABLE_POINTS, self::AVAILABLE_COINS, self::LOGIN_COUNT => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
                SegmentRuleOperator::GT,
                SegmentRuleOperator::GTE,
                SegmentRuleOperator::LT,
                SegmentRuleOperator::LTE,
                SegmentRuleOperator::BETWEEN,
                SegmentRuleOperator::IN,
                SegmentRuleOperator::NOT_IN,
            ],
            self::LAST_LOGIN_AT, self::LAST_ACTIVE_AT, self::CREATED_AT => [
                SegmentRuleOperator::GT,
                SegmentRuleOperator::GTE,
                SegmentRuleOperator::LT,
                SegmentRuleOperator::LTE,
                SegmentRuleOperator::BETWEEN,
                SegmentRuleOperator::IS_NULL,
                SegmentRuleOperator::IS_NOT_NULL,
            ],
        };
    }

    /**
     * 前端展示标签
     */
    public function label(): string
    {
        return match ($this) {
            self::STATUS => '账号状态',
            self::IDENTITY_STATUS => '实名认证状态',
            self::IDENTITY_TYPE => '实名认证类型',
            self::VIP_EXPIRES_AT => 'VIP 到期时间',
            self::LAST_LOGIN_AT => '最近登录时间',
            self::LAST_ACTIVE_AT => '最后活跃时间',
            self::CREATED_AT => '注册时间',
            self::AVAILABLE_POINTS => '可用积分',
            self::AVAILABLE_COINS => '可用金币',
            self::LOGIN_COUNT => '累计登录次数',
        };
    }

    /**
     * 解析规则 JSON 中的字面量值。
     *
     *  - "now" / "today" / "now-7d" / "today-1d" 这类特殊字符串被解析为 Carbon
     *  - 其他值原样返回（int / string / array）
     *  - 数组元素逐项解析（用于 between / in / not_in）
     */
    public function resolveValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->resolveValue($v), $value);
        }

        if (! is_string($value) || $this->valueType() !== 'datetime') {
            return $value;
        }

        return match (true) {
            $value === 'now' => \Illuminate\Support\Carbon::now(),
            $value === 'today' => \Illuminate\Support\Carbon::today(),
            preg_match('/^now-(\d+)([hd])$/', $value, $m) === 1 => \Illuminate\Support\Carbon::now()
                ->sub($m[2] === 'h' ? "{$m[1]} hours" : "{$m[1]} days"),
            preg_match('/^today-(\d+)(d)$/', $value, $m) === 1 => \Illuminate\Support\Carbon::today()
                ->subDays((int) $m[1]),
            default => $value,
        };
    }
}
