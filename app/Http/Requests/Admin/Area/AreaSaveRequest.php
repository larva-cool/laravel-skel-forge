<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Area;

use App\Models\System\Area;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 地区保存请求（创建/编辑）
 *
 * @property-read int|null $parent_id 父地区ID，null 表示顶级
 * @property-read string $name 地区名称
 * @property-read int|null $area_code 区域编码
 * @property-read float|null $lat 纬度
 * @property-read float|null $lng 经度
 * @property-read string|null $city_code 区号
 * @property-read int $sort 排序权重
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AreaSaveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'min:1', Rule::exists(Area::class, 'id')],
            'name' => ['required', 'string', 'max:100'],
            'area_code' => ['nullable', 'integer'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'city_code' => ['nullable', 'string', 'max:20'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
