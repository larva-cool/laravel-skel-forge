<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Common;

use App\Rules\PhoneRule;
use App\Rules\SmsCaptchaSendCheckRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 手机验证码
 *
 * @property-read string $phone 手机号
 * @property-read string $scene 场景
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class SmsCaptchaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', new PhoneRule, new SmsCaptchaSendCheckRule($this->ip())],
            'scene' => ['string'],
        ];
    }
}
