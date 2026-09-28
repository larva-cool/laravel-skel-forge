<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Agreement;

use App\Enums\AgreementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 协议保存请求（创建/编辑）
 *
 * @property-read string $title 协议标题
 * @property-read string $category 协议类别
 * @property-read string $content 协议内容
 * @property-read string $version 版本号
 * @property-read AgreementStatus $status 状态
 * @property-read string|null $effective_at 生效时间
 * @property-read int $sort 排序权重
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AgreementSaveRequest extends FormRequest
{
    /**
     * 注入创建人管理员 ID，使控制器可直接用 validated() 写入模型。
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['admin_id' => $this->user('admin')?->id]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string'],
            'version' => ['required', 'string', 'max:20'],
            'status' => ['sometimes', 'string', Rule::enum(AgreementStatus::class)],
            'effective_at' => ['sometimes', 'nullable', 'date'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'admin_id' => ['nullable', 'integer'],
        ];
    }
}
