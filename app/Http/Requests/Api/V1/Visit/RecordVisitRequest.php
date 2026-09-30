<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Visit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 记录一次访问
 *
 * 由后端在 prepareForValidation 中合并 user_id 与 ip，前端只传 source_type / source_id。
 *
 * @property-read int $user_id 用户ID
 * @property-read string $source_type 资源类型
 * @property-read int $source_id 资源ID
 * @property-read string|null $ip 访问IP
 */
class RecordVisitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 准备验证数据
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->user()->id,
            'ip' => $this->ip(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['numeric'],
            'source_type' => ['required', 'string', Rule::in(source_types())],
            'source_id' => ['required', 'integer', 'min:1'],
            'ip' => ['nullable', 'string'],
        ];
    }
}
