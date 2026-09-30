<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Rules\SensitiveTextRule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 用户提交举报
 *
 * @property-read int $user_id 举报人用户ID
 * @property-read string $target_type 被举报对象 morph alias（comment / user）
 * @property-read int $target_id 被举报对象 ID
 * @property-read string $category 举报分类（取自字典 report_category）
 * @property-read string|null $reason 举报描述
 * @property-read array<int, string>|null $evidence 证据图片 URL 数组
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class StoreReportRequest extends FormRequest
{
    /**
     * 仅允许举报当前支持的对象类型
     */
    private const REPORTABLE_TYPES = ['comment', 'user'];

    /**
     * 举报分类允许值（与 dict report_category 一致）
     */
    private const CATEGORIES = [
        'pornography', 'violence', 'political', 'ad',
        'insult', 'fake', 'infringement', 'illegal', 'other',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 注入 user_id，并按 morph alias 解析目标类型 → 全限定类名。
     */
    protected function prepareForValidation(): void
    {
        $alias = (string) $this->input('target_type');
        $class = Relation::getMorphedModel($alias);

        $this->merge([
            'user_id' => $this->user()?->id,
            'target_type_morph' => $class ?: $alias,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // 校验时目标对象必须存在；class 不存在则 target_id 校验会被跳过
        $class = Relation::getMorphedModel((string) $this->input('target_type'));
        $targetIdRules = ['required', 'integer', 'min:1'];
        if (is_string($class) && class_exists($class)) {
            $targetIdRules[] = 'exists:'.$this->tableForModel($class).',id';
        }

        return [
            'user_id' => ['required', 'integer'],
            'target_type' => ['required', 'string', Rule::in(self::REPORTABLE_TYPES)],
            'target_id' => $targetIdRules,
            'category' => ['required', 'string', Rule::in(self::CATEGORIES)],
            'reason' => ['nullable', 'string', 'max:500', new SensitiveTextRule],
            'evidence' => ['nullable', 'array', 'max:9'],
            'evidence.*' => ['string', 'url', 'max:500'],
        ];
    }

    /**
     * 解析 model 对应的数据表名（用于 exists 校验）
     */
    private function tableForModel(string $class): string
    {
        return (new $class)->getTable();
    }

    /**
     * 提供给控制器使用的已校验数据
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $class = (string) $this->input('target_type_morph');

        return [
            'category' => (string) $this->input('category'),
            'reason' => $this->input('reason'),
            'evidence' => $this->input('evidence'),
            'target_class' => $class,
        ];
    }
}
