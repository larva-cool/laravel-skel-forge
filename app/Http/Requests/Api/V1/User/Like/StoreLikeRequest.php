<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User\Like;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 添加点赞
 *
 * @property-read int $user_id 用户ID
 * @property-read string $source_type 资源类型
 * @property-read int $source_id 资源ID
 * @property-read array<string, mixed>|null $extra 扩展信息
 */
class StoreLikeRequest extends FormRequest
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
        $this->merge(['user_id' => $this->user()->id]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['numeric'],
            'source_type' => ['required', 'string', Rule::in(source_types())],
            'source_id' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('likes')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('source_type', $this->post('source_type'))
                ),
            ],
            'extra' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_id.unique' => '已经点赞过该资源',
        ];
    }
}
