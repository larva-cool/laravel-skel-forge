<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User\Identity;

use App\Enums\IdentityType;
use App\Rules\IdCardRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 用户提交实名认证请求
 *
 * @property-read string $type 认证类型
 * @property-read string|null $real_name 真实姓名
 * @property-read string|null $id_card_no 身份证号
 * @property-read int|null $id_card_front_id 身份证人像面附件ID
 * @property-read int|null $id_card_back_id 身份证国徽面附件ID
 * @property-read int|null $handheld_id 手持身份证附件ID
 * @property-read string|null $company_name 企业名称
 * @property-read string|null $credit_code 统一社会信用代码
 * @property-read string|null $legal_person_name 法人姓名
 * @property-read string|null $legal_person_id_card_no 法人身份证号
 * @property-read int|null $business_license_id 营业执照附件ID
 * @property-read int|null $opening_permit_id 开户许可证附件ID
 * @property-read string|null $bank_account 对公账户
 * @property-read string|null $bank_name 开户行
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SubmitIdentityRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(IdentityType::class)],
            'real_name' => ['required_if:type,personal', 'nullable', 'string', 'max:64'],
            'id_card_no' => [
                'required_if:type,personal', 'nullable', 'string', 'max:32', new IdCardRule,
            ],
            'id_card_front_id' => ['required_if:type,personal', 'nullable', 'integer', 'exists:attachments,id'],
            'id_card_back_id' => ['nullable', 'integer', 'exists:attachments,id'],
            'handheld_id' => ['nullable', 'integer', 'exists:attachments,id'],
            'company_name' => ['required_if:type,enterprise', 'nullable', 'string', 'max:128'],
            'credit_code' => [
                'required_if:type,enterprise', 'nullable', 'string', 'max:32',
                'regex:/^[0-9A-HJ-NPQRTUWXY]{18}$/i',
            ],
            'legal_person_name' => ['required_if:type,enterprise', 'nullable', 'string', 'max:64'],
            'legal_person_id_card_no' => [
                'required_if:type,enterprise', 'nullable', 'string', 'max:32', new IdCardRule,
            ],
            'business_license_id' => ['required_if:type,enterprise', 'nullable', 'integer', 'exists:attachments,id'],
            'opening_permit_id' => ['nullable', 'integer', 'exists:attachments,id'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:64'],
        ];
    }
}
