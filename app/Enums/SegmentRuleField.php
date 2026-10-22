<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;
use Illuminate\Database\Eloquent\Builder;

/**
 * 分群规则可用字段枚举
 *
 * 列出规则条件允许引用的用户字段，每个字段声明：
 *  - column：实际数据库列名（聚合/存在性字段为关联表上的目标列）
 *  - queryType：编译方式（主表/关联列比较、关系存在性、关系计数、关系求和）
 *  - valueType：值类型（int/number/string/datetime）
 *  - allowedOperators：该字段允许的操作符白名单
 *  - resolveValue：把规则 JSON 中的"特殊值"（now / today / now-7d）解析为可比较的字面量
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum SegmentRuleField: string implements \JsonSerializable
{
    use HasLabel;

    /** 主表/关联列直接比较 */
    public const QUERY_COLUMN = 'column';

    /** 关系存在性（已绑定/未绑定） */
    public const QUERY_EXISTS = 'exists';

    /** 关系计数（充值次数/提现次数/邀请人数） */
    public const QUERY_COUNT = 'count';

    /** 关系求和（累计充值金额） */
    public const QUERY_SUM = 'sum';

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
    case GENDER = 'gender';
    case BIRTHDAY = 'birthday';
    case BALANCE = 'balance';
    case FROZEN_BALANCE = 'frozen_balance';
    case PROVINCE_ID = 'province_id';
    case CITY_ID = 'city_id';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case USERNAME = 'username';
    case EMAIL_VERIFIED_AT = 'email_verified_at';
    case SOCIAL_PROVIDER = 'social_provider';
    case RECHARGE_COUNT = 'recharge_count';
    case RECHARGE_TOTAL = 'recharge_total';
    case WITHDRAW_COUNT = 'withdraw_count';
    case INVITE_COUNT = 'invite_count';

    /**
     * 数据库列名（聚合/存在性字段为关联表上的目标列）
     */
    public function column(): string
    {
        return match ($this) {
            self::SOCIAL_PROVIDER => 'provider',
            self::RECHARGE_TOTAL => 'amount',
            default => $this->value,
        };
    }

    /**
     * 关联关系名（null 表示字段在 users 主表上或为聚合/存在性字段）
     *
     * gender / birthday / province_id / city_id 位于 user_profiles 表，通过 profile 关联；
     * email_verified_at 位于 user_extras 表，通过 extra 关联
     */
    public function relation(): ?string
    {
        return match ($this) {
            self::GENDER, self::BIRTHDAY, self::PROVINCE_ID, self::CITY_ID => 'profile',
            self::EMAIL_VERIFIED_AT => 'extra',
            default => null,
        };
    }

    /**
     * 查询编译方式
     */
    public function queryType(): string
    {
        return match ($this) {
            self::SOCIAL_PROVIDER => self::QUERY_EXISTS,
            self::RECHARGE_COUNT, self::WITHDRAW_COUNT, self::INVITE_COUNT => self::QUERY_COUNT,
            self::RECHARGE_TOTAL => self::QUERY_SUM,
            default => self::QUERY_COLUMN,
        };
    }

    /**
     * 聚合/存在性规则使用的关联名
     */
    public function aggregateRelation(): ?string
    {
        return match ($this) {
            self::SOCIAL_PROVIDER => 'socials',
            self::RECHARGE_COUNT, self::RECHARGE_TOTAL => 'recharges',
            self::WITHDRAW_COUNT => 'withdraws',
            self::INVITE_COUNT => 'invitees',
            default => null,
        };
    }

    /**
     * 聚合/存在性规则的附加约束闭包（如充值仅统计已到账）
     *
     * @return (\Closure(Builder<\Illuminate\Database\Eloquent\Model>): void)|null
     */
    public function aggregateConstraint(): ?\Closure
    {
        return match ($this) {
            self::RECHARGE_COUNT, self::RECHARGE_TOTAL => static fn (Builder $q) => $q->where('pay_status', RechargeStatus::SUCCESS->value),
            default => null,
        };
    }

    /**
     * 求和子查询的分组列（SUM 场景 group by 用）
     */
    public function aggregateGroupColumn(): ?string
    {
        return match ($this) {
            self::RECHARGE_TOTAL => 'owner_id',
            default => null,
        };
    }

    /**
     * 值类型：int / number / string / datetime
     */
    public function valueType(): string
    {
        return match ($this) {
            self::STATUS, self::GENDER, self::AVAILABLE_POINTS, self::AVAILABLE_COINS, self::LOGIN_COUNT,
            self::PROVINCE_ID, self::CITY_ID,
            self::RECHARGE_COUNT, self::WITHDRAW_COUNT, self::INVITE_COUNT => 'int',
            self::BALANCE, self::FROZEN_BALANCE, self::RECHARGE_TOTAL => 'number',
            self::IDENTITY_STATUS, self::IDENTITY_TYPE,
            self::EMAIL, self::PHONE, self::USERNAME, self::SOCIAL_PROVIDER => 'string',
            self::VIP_EXPIRES_AT, self::LAST_LOGIN_AT, self::LAST_ACTIVE_AT, self::CREATED_AT,
            self::BIRTHDAY, self::EMAIL_VERIFIED_AT => 'datetime',
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
            self::GENDER => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
                SegmentRuleOperator::IN,
                SegmentRuleOperator::NOT_IN,
                SegmentRuleOperator::IS_NULL,
                SegmentRuleOperator::IS_NOT_NULL,
            ],
            self::BIRTHDAY => [
                SegmentRuleOperator::GT,
                SegmentRuleOperator::GTE,
                SegmentRuleOperator::LT,
                SegmentRuleOperator::LTE,
                SegmentRuleOperator::BETWEEN,
                SegmentRuleOperator::IS_NULL,
                SegmentRuleOperator::IS_NOT_NULL,
            ],
            self::BALANCE, self::FROZEN_BALANCE => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
                SegmentRuleOperator::GT,
                SegmentRuleOperator::GTE,
                SegmentRuleOperator::LT,
                SegmentRuleOperator::LTE,
                SegmentRuleOperator::BETWEEN,
            ],
            self::PROVINCE_ID, self::CITY_ID => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
                SegmentRuleOperator::IN,
                SegmentRuleOperator::NOT_IN,
                SegmentRuleOperator::IS_NULL,
                SegmentRuleOperator::IS_NOT_NULL,
            ],
            self::EMAIL, self::PHONE, self::USERNAME, self::EMAIL_VERIFIED_AT => [
                SegmentRuleOperator::IS_NULL,
                SegmentRuleOperator::IS_NOT_NULL,
            ],
            self::SOCIAL_PROVIDER => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
            ],
            self::RECHARGE_COUNT, self::WITHDRAW_COUNT, self::INVITE_COUNT => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
                SegmentRuleOperator::GT,
                SegmentRuleOperator::GTE,
                SegmentRuleOperator::LT,
                SegmentRuleOperator::LTE,
            ],
            self::RECHARGE_TOTAL => [
                SegmentRuleOperator::EQ,
                SegmentRuleOperator::NEQ,
                SegmentRuleOperator::GT,
                SegmentRuleOperator::GTE,
                SegmentRuleOperator::LT,
                SegmentRuleOperator::LTE,
                SegmentRuleOperator::BETWEEN,
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
            self::GENDER => '性别',
            self::BIRTHDAY => '生日',
            self::BALANCE => '可用余额',
            self::FROZEN_BALANCE => '冻结余额',
            self::PROVINCE_ID => '所在省份',
            self::CITY_ID => '所在城市',
            self::EMAIL => '邮箱绑定',
            self::PHONE => '手机号绑定',
            self::USERNAME => '用户名设置',
            self::EMAIL_VERIFIED_AT => '邮箱验证时间',
            self::SOCIAL_PROVIDER => '社交平台绑定',
            self::RECHARGE_COUNT => '充值次数',
            self::RECHARGE_TOTAL => '累计充值金额',
            self::WITHDRAW_COUNT => '提现次数',
            self::INVITE_COUNT => '邀请人数',
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
