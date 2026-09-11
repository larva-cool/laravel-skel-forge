<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\TaskGroup;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read string|null $keyword 关键词搜索
 * @property-read string|null $status 状态筛选
 */
class TaskGroupIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
