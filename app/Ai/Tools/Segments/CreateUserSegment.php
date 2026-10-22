<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Segments;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Enums\SegmentType;
use App\Models\UserSegment\Segment;
use App\Services\Segment\SegmentRuleEvaluator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 用户分群创建工具。
 *
 * 支持创建手动分群与规则分群。规则分群必须提供全局唯一编码与规则定义，
 * 创建后需通过 SyncUserSegment 或定时任务同步成员。敏感写操作，
 * 执行前需要管理员二次确认。
 */
class CreateUserSegment implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 标签样式白名单
     *
     * @var array<int, string>
     */
    private const LABEL_TYPES = ['default', 'primary', 'success', 'warning', 'danger', 'info'];

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'segments.create';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '创建一个新的用户分群。type=1 为手动分群（成员由管理员手动打标维护）；type=2 为规则分群（必须同时提供全局唯一的 code 与 rule 规则定义，创建后需调用 SyncUserSegment 触发成员同步或等待定时任务自动同步）。该操作为敏感写操作，执行前会展示创建摘要并请求二次确认。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->required()
                ->max(64)
                ->description('分群名称'),
            'type' => $schema->integer()
                ->required()
                ->enum([1, 2])
                ->description('分群类型：1=手动分群，2=规则分群'),
            'code' => $schema->string()
                ->max(64)
                ->description('分群编码，仅字母/数字/下划线，全局唯一。规则分群（type=2）必填，手动分群可留空'),
            'label_type' => $schema->string()
                ->enum(self::LABEL_TYPES)
                ->description('标签样式，默认 default'),
            'group' => $schema->string()
                ->max(32)
                ->description('所属分组（可选）'),
            'description' => $schema->string()
                ->max(500)
                ->description('分群描述（可选）'),
            'sort' => $schema->integer()
                ->min(0)
                ->description('排序权重，越小越靠前，默认 1000'),
            'status' => $schema->boolean()
                ->description('是否启用，默认 true'),
            'rule' => $schema->object(fn (JsonSchema $rule) => [
                'logic' => $rule->string()
                    ->enum(['and', 'or'])
                    ->description('条件组合逻辑：and=全部满足，or=任一满足。默认 and'),
                'conditions' => $rule->array()
                    ->items($rule->object(fn (JsonSchema $cond) => [
                        'field' => $cond->string()
                            ->required()
                            ->enum(array_map(static fn (SegmentRuleField $field) => $field->value, SegmentRuleField::cases()))
                            ->description('规则字段'),
                        'op' => $cond->string()
                            ->required()
                            ->enum(array_map(static fn (SegmentRuleOperator $op) => $op->value, SegmentRuleOperator::cases()))
                            ->description('操作符。注意各字段支持的操作符不同；datetime 类字段的值支持 now/today/now-7d/today-1d 等特殊字面量'),
                        'value' => $cond->union(['string', 'integer', 'number', 'boolean', 'array'])
                            ->description('比较值。between/in/not_in 传数组（between 恰好 2 个值）；is_null/is_not_null 无需传'),
                    ]))
                    ->description('条件数组'),
            ])
                ->description('规则定义，仅规则分群（type=2）必填'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $name = trim($request->string('name')->toString());
        if ($name === '') {
            return false;
        }

        $type = SegmentType::tryFrom($request->integer('type'));
        $message = "即将创建{$type?->label()}分群「{$name}」";

        if ($type?->isRule()) {
            $code = trim($request->string('code')->toString());
            $conditions = $request->array('rule')['conditions'] ?? [];
            $message .= "（编码：{$code}，规则条件 ".count((array) $conditions).' 条）';
        }

        return Approval::required($message.'，创建后立即可在分群列表中使用。');
    }

    /**
     * 执行创建。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $name = trim($request->string('name')->toString());
        if ($name === '') {
            return '分群名称不能为空，操作已取消。';
        }

        $type = SegmentType::tryFrom($request->integer('type'));
        if (! $type) {
            return '分群类型非法（仅支持 1=手动、2=规则），操作已取消。';
        }

        $code = trim($request->string('code')->toString());
        if ($type->isRule() && $code === '') {
            return '规则分群必须提供分群编码 code，操作已取消。';
        }
        if ($code !== '') {
            if (! preg_match('/^[A-Za-z0-9_]+$/', $code)) {
                return '分群编码仅支持字母、数字、下划线，操作已取消。';
            }
            if (Segment::query()->where('code', $code)->exists()) {
                return "分群编码「{$code}」已被占用，请更换后重试。";
            }
        }

        $rule = null;
        if ($type->isRule()) {
            $rule = $request->array('rule');
            if ($rule === []) {
                return '规则分群必须提供 rule 规则定义，操作已取消。';
            }
            $rule['version'] = SegmentRuleEvaluator::VERSION;
            try {
                (new SegmentRuleEvaluator)->validate($rule);
            } catch (InvalidArgumentException $e) {
                return "规则定义非法：{$e->getMessage()}，操作已取消。";
            }
        }

        $labelType = $request->string('label_type')->toString() ?: 'default';
        if (! in_array($labelType, self::LABEL_TYPES, true)) {
            $labelType = 'default';
        }

        $segment = Segment::query()->create([
            'code' => $code !== '' ? $code : null,
            'name' => $name,
            'label_type' => $labelType,
            'type' => $type,
            'group' => trim($request->string('group')->toString()) ?: null,
            'description' => trim($request->string('description')->toString()) ?: null,
            'sort' => $request->has('sort') ? max($request->integer('sort'), 0) : 1000,
            'status' => $request->has('status') ? $request->boolean('status') : true,
            'rule' => $rule,
        ]);

        $message = "已创建{$segment->type->label()}分群「{$segment->name}」（ID：{$segment->id}";
        if ($segment->code) {
            $message .= "，编码：{$segment->code}";
        }
        $message .= '，状态：'.($segment->status ? '启用' : '禁用').'）。';

        if ($segment->type->isRule()) {
            $message .= '如需立即匹配成员，可调用 SyncUserSegment 触发同步，或等待定时任务自动同步。';
        }

        return $message;
    }
}
