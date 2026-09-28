<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Enums\Gender;
use App\Models\System\Area;
use App\Rules\NameRule;
use App\Rules\SensitiveTextRule;
use App\Services\SensitiveWordService;
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
            'name' => ['nullable', 'string', 'min:2', 'max:20', new NameRule, new SensitiveTextRule('strict')],
            'birthday' => ['sometimes', 'date'],
            'gender' => ['nullable', 'integer', new Enum(Gender::class)],
            'province_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')],
            'district_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')],
            'website' => ['nullable', 'url', 'max:200'],
            'intro' => ['nullable', 'string', 'max:200', new SensitiveTextRule('block', false)],
            'bio' => ['nullable', 'string', 'max:100', new SensitiveTextRule('block', false)],
        ];
    }

    /**
     * 验证前对 intro/bio 做敏感词替换。
     *
     * name 字段使用 strict 模式，所有敏感词均由 SensitiveTextRule 拦截，无需替换。
     * intro/bio 使用 block 模式：replace 级别的词在此提前替换，block 级别的词
     * 仍由 SensitiveTextRule 拦截。替换发生在验证前，确保 $request->safe() 能
     * 拿到替换后的文本。
     */
    protected function prepareForValidation(): void
    {
        $service = app(SensitiveWordService::class);
        $merge = [];
        $hitWords = [];

        foreach (['intro', 'bio'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && $value !== '') {
                $result = $service->detect($value);
                $merge[$field] = $result->filtered;
                $hitWords = array_merge($hitWords, $result->hitWords());
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }

        if ($hitWords !== []) {
            try {
                $service->incrementHitCount(array_unique($hitWords));
            } catch (\Throwable) {
            }
        }
    }
}
