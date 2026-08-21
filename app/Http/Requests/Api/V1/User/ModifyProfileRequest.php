<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Enums\Gender;
use App\Models\System\Area;
use App\Rules\NameRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * 修改资料请求
 *
 * @property-read string $name 昵称
 * @property-read string $birthday 生日
 * @property-read int $gender 性别：0保密/1男/2女
 * @property-read int $province_id 省 ID
 * @property-read int $city_id 市 ID
 * @property-read int $district_id 区县ID
 * @property-read string $website 个人网站
 * @property-read string $intro 个人介绍
 * @property-read string $bio 个性签名
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ModifyProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', new NameRule],
            'birthday' => ['sometimes', 'date'],
            'gender' => ['nullable', 'integer', new Enum(Gender::class)],
            'province_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')],
            'district_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')],
            'website' => ['nullable', 'url'],
            'intro' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
        ];
    }
}
