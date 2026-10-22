<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services\Segment;

use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * 分群规则编译器
 *
 * 将 Segment::rule JSON 结构编译为可对 User::query() 应用的 Eloquent Builder 闭包。
 * 纯函数：相同的输入永远返回等价的闭包，方便测试与缓存。
 *
 * 规则结构：
 * ```json
 * {
 *   "version": 1,
 *   "logic": "and" | "or",
 *   "conditions": [
 *     {"field": "status", "op": "=", "value": 1},
 *     {"field": "vip_expires_at", "op": ">", "value": "now"},
 *     {"field": "last_login_at", "op": "<", "value": "now-7d"}
 *   ]
 * }
 * ```
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentRuleEvaluator
{
    public const VERSION = 1;
    public const LOGIC_AND = 'and';
    public const LOGIC_OR = 'or';

    /**
     * 编译规则为闭包，闭包对 User::query() 应用条件。
     *
     * @param  array<string,mixed>|null  $rule
     * @return \Closure(\Illuminate\Database\Eloquent\Builder<\App\Models\User>): \Illuminate\Database\Eloquent\Builder<\App\Models\User>
     */
    public function compile(?array $rule): \Closure
    {
        if ($rule === null || $rule === []) {
            // 空规则不施加任何条件
            return static fn (Builder $q) => $q;
        }

        $this->validate($rule);

        $logic = strtolower((string) ($rule['logic'] ?? self::LOGIC_AND));
        $conditions = $rule['conditions'] ?? [];

        $appliers = array_map(
            fn (array $cond) => $this->compileCondition($cond),
            $conditions
        );

        if ($logic === self::LOGIC_OR) {
            return static function (Builder $q) use ($appliers): void {
                $q->where(function (Builder $sub) use ($appliers): void {
                    foreach ($appliers as $i => $apply) {
                        $sub->orWhere(function (Builder $inner) use ($apply): void {
                            $apply($inner);
                        });
                    }
                });
            };
        }

        // 默认 AND
        return static function (Builder $q) use ($appliers): void {
            $q->where(function (Builder $sub) use ($appliers): void {
                foreach ($appliers as $apply) {
                    $sub->where(function (Builder $inner) use ($apply): void {
                        $apply($inner);
                    });
                }
            });
        };
    }

    /**
     * 校验规则结构
     *
     * @param  array<string,mixed>  $rule
     */
    public function validate(array $rule): void
    {
        if (! isset($rule['conditions']) || ! is_array($rule['conditions'])) {
            throw new InvalidArgumentException('规则缺少 conditions 数组');
        }

        if ($rule['conditions'] === []) {
            // 空 conditions 视作"无条件"，不抛错（compile 会直接返回 identity 闭包）
            return;
        }

        $logic = $rule['logic'] ?? self::LOGIC_AND;
        if (! in_array($logic, [self::LOGIC_AND, self::LOGIC_OR], true)) {
            throw new InvalidArgumentException("不支持的 logic: {$logic}");
        }

        foreach ($rule['conditions'] as $i => $cond) {
            if (! is_array($cond)) {
                throw new InvalidArgumentException("第 {$i} 条条件不是数组");
            }
            $this->validateCondition($cond, $i);
        }
    }

    /**
     * 编译单条条件为闭包（按字段查询类型分发）
     *
     * @param  array<string,mixed>  $condition
     * @return \Closure(\Illuminate\Database\Eloquent\Builder<\App\Models\User>): void
     */
    protected function compileCondition(array $condition): \Closure
    {
        $fieldEnum = SegmentRuleField::from((string) $condition['field']);
        $operator = SegmentRuleOperator::from((string) $condition['op']);
        $rawValue = $condition['value'] ?? null;

        if (! in_array($operator, $fieldEnum->allowedOperators(), true)) {
            throw new InvalidArgumentException("字段 {$fieldEnum->value} 不支持操作符 {$operator->value}");
        }

        return match ($fieldEnum->queryType()) {
            SegmentRuleField::QUERY_EXISTS => $this->compileExistence($fieldEnum, $operator, $rawValue),
            SegmentRuleField::QUERY_COUNT => $this->compileCount($fieldEnum, $operator, $rawValue),
            SegmentRuleField::QUERY_SUM => $this->compileSum($fieldEnum, $operator, $rawValue),
            default => $this->compileColumnCondition($fieldEnum, $operator, $rawValue),
        };
    }

    /**
     * 编译主表/关联列比较条件
     *
     * @return \Closure(\Illuminate\Database\Eloquent\Builder<\App\Models\User>): void
     */
    protected function compileColumnCondition(SegmentRuleField $fieldEnum, SegmentRuleOperator $operator, mixed $rawValue): \Closure
    {
        $column = $fieldEnum->column();

        // 关联字段（如 gender / birthday 位于 user_profiles 表）通过 whereHas 包装
        $relation = $fieldEnum->relation();

        if ($operator->isNullCheck()) {
            $inner = static function (Builder $q) use ($column, $operator): void {
                if ($operator === SegmentRuleOperator::IS_NULL) {
                    $q->whereNull($column);
                } else {
                    $q->whereNotNull($column);
                }
            };

            return $relation !== null
                ? static fn (Builder $q) => $q->whereHas($relation, fn (Builder $sub) => $inner($sub))
                : $inner;
        }

        if ($operator->requiresMultipleValues()) {
            if (! is_array($rawValue)) {
                throw new InvalidArgumentException("操作符 {$operator->value} 需要数组值");
            }
            $resolved = $fieldEnum->resolveValue($rawValue);

            $inner = match ($operator) {
                SegmentRuleOperator::BETWEEN => static function (Builder $q) use ($column, $resolved): void {
                    if (count($resolved) !== 2) {
                        throw new InvalidArgumentException('between 需要恰好 2 个值');
                    }
                    [$from, $to] = $resolved;
                    $q->whereBetween($column, [$from, $to]);
                },
                SegmentRuleOperator::IN => static function (Builder $q) use ($column, $resolved): void {
                    $q->whereIn($column, $resolved);
                },
                SegmentRuleOperator::NOT_IN => static function (Builder $q) use ($column, $resolved): void {
                    $q->whereNotIn($column, $resolved);
                },
                default => throw new InvalidArgumentException("未实现的多值操作符: {$operator->value}"),
            };

            return $relation !== null
                ? static fn (Builder $q) => $q->whereHas($relation, fn (Builder $sub) => $inner($sub))
                : $inner;
        }

        // 单值比较
        $resolved = $fieldEnum->resolveValue($rawValue);

        $inner = match ($operator) {
            SegmentRuleOperator::EQ => static fn (Builder $q) => $q->where($column, '=', $resolved),
            SegmentRuleOperator::NEQ => static fn (Builder $q) => $q->where($column, '!=', $resolved),
            SegmentRuleOperator::GT => static fn (Builder $q) => $q->where($column, '>', $resolved),
            SegmentRuleOperator::GTE => static fn (Builder $q) => $q->where($column, '>=', $resolved),
            SegmentRuleOperator::LT => static fn (Builder $q) => $q->where($column, '<', $resolved),
            SegmentRuleOperator::LTE => static fn (Builder $q) => $q->where($column, '<=', $resolved),
            default => throw new InvalidArgumentException("未实现的操作符: {$operator->value}"),
        };

        return $relation !== null
            ? static fn (Builder $q) => $q->whereHas($relation, fn (Builder $sub) => $inner($sub))
            : $inner;
    }

    /**
     * 编译关系存在性条件（如已绑定/未绑定某社交平台）
     *
     * @return \Closure(\Illuminate\Database\Eloquent\Builder<\App\Models\User>): void
     */
    protected function compileExistence(SegmentRuleField $field, SegmentRuleOperator $operator, mixed $rawValue): \Closure
    {
        $relation = $field->aggregateRelation();
        $column = $field->column();
        $resolved = $field->resolveValue($rawValue);

        return match ($operator) {
            SegmentRuleOperator::EQ => static fn (Builder $q) => $q->whereHas(
                $relation,
                static fn (Builder $sub) => $sub->where($column, $resolved)
            ),
            SegmentRuleOperator::NEQ => static fn (Builder $q) => $q->whereDoesntHave(
                $relation,
                static fn (Builder $sub) => $sub->where($column, $resolved)
            ),
            default => throw new InvalidArgumentException("字段 {$field->value} 不支持操作符 {$operator->value}"),
        };
    }

    /**
     * 编译关系计数条件（如充值次数 / 提现次数 / 邀请人数）
     *
     * @return \Closure(\Illuminate\Database\Eloquent\Builder<\App\Models\User>): void
     */
    protected function compileCount(SegmentRuleField $field, SegmentRuleOperator $operator, mixed $rawValue): \Closure
    {
        $relation = $field->aggregateRelation();
        $constraint = $field->aggregateConstraint();
        $count = max(0, (int) $rawValue);

        return match ($operator) {
            SegmentRuleOperator::EQ, SegmentRuleOperator::NEQ,
            SegmentRuleOperator::GT, SegmentRuleOperator::GTE,
            SegmentRuleOperator::LT, SegmentRuleOperator::LTE => static fn (Builder $q) => $q->whereHas($relation, $constraint, $operator->value, $count),
            default => throw new InvalidArgumentException("字段 {$field->value} 不支持操作符 {$operator->value}"),
        };
    }

    /**
     * 编译关系求和条件（如累计充值金额）
     *
     * 通过 whereHas + groupBy/having 子查询实现：子查询按持有者分组后以 SUM 聚合值
     * 过滤，存在分组行即表示该用户满足求和条件。注意：无任何明细记录的用户不产生
     * 分组行，不会被「= 0」类条件命中。
     *
     * @return \Closure(\Illuminate\Database\Eloquent\Builder<\App\Models\User>): void
     */
    protected function compileSum(SegmentRuleField $field, SegmentRuleOperator $operator, mixed $rawValue): \Closure
    {
        $relation = $field->aggregateRelation();
        $constraint = $field->aggregateConstraint();
        $column = $field->column();
        $groupColumn = $field->aggregateGroupColumn();

        if ($operator === SegmentRuleOperator::BETWEEN) {
            if (! is_array($rawValue) || count($rawValue) !== 2) {
                throw new InvalidArgumentException('between 需要恰好 2 个值');
            }
            $raw = "COALESCE(SUM({$column}), 0) BETWEEN ? AND ?";
            $bindings = array_values($rawValue);
        } elseif (in_array($operator, [
            SegmentRuleOperator::EQ, SegmentRuleOperator::NEQ,
            SegmentRuleOperator::GT, SegmentRuleOperator::GTE,
            SegmentRuleOperator::LT, SegmentRuleOperator::LTE,
        ], true)) {
            $raw = "COALESCE(SUM({$column}), 0) {$operator->value} ?";
            $bindings = [$rawValue];
        } else {
            throw new InvalidArgumentException("字段 {$field->value} 不支持操作符 {$operator->value}");
        }

        return static function (Builder $q) use ($relation, $constraint, $groupColumn, $raw, $bindings): void {
            $q->whereHas($relation, static function (Builder $sub) use ($constraint, $groupColumn, $raw, $bindings): void {
                if ($constraint) {
                    $constraint($sub);
                }
                $sub->select($groupColumn)->groupBy($groupColumn)->havingRaw($raw, $bindings);
            });
        };
    }

    /**
     * 校验单条条件（仅结构 + 字段/操作符白名单）
     *
     * @param  array<string,mixed>  $cond
     */
    protected function validateCondition(array $cond, int $index): void
    {
        if (! isset($cond['field'], $cond['op'])) {
            throw new InvalidArgumentException("第 {$index} 条条件缺少 field 或 op");
        }

        $field = SegmentRuleField::tryFrom((string) $cond['field']);
        if ($field === null) {
            throw new InvalidArgumentException("第 {$index} 条条件字段非法: {$cond['field']}");
        }

        $operator = SegmentRuleOperator::tryFrom((string) $cond['op']);
        if ($operator === null) {
            throw new InvalidArgumentException("第 {$index} 条条件操作符非法: {$cond['op']}");
        }

        if (! in_array($operator, $field->allowedOperators(), true)) {
            throw new InvalidArgumentException(
                "第 {$index} 条条件: 字段 {$field->value} 不支持操作符 {$operator->value}"
            );
        }

        if (! $operator->isNullCheck() && ! array_key_exists('value', $cond)) {
            throw new InvalidArgumentException("第 {$index} 条条件缺少 value");
        }

        if ($operator->requiresMultipleValues() && ! is_array($cond['value'] ?? null)) {
            throw new InvalidArgumentException("第 {$index} 条条件操作符 {$operator->value} 需要数组值");
        }
    }
}
