<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\User;

use App\Enums\IdentityType;
use App\Enums\ReviewStatus;
use App\Models\Admin\Admin;
use App\Models\Model;
use App\Models\System\Attachment;
use App\Policies\IdentityVerificationPolicy;
use Database\Factories\User\IdentityVerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 实名认证记录
 *
 * @property int $id 记录ID
 * @property string $verifiable_type 申请人类型（多态）
 * @property int $verifiable_id 申请人ID
 * @property IdentityType $type 认证类型
 * @property ReviewStatus $status 审核状态
 * @property string|null $real_name 真实姓名（个人）
 * @property string|null $id_card_no 身份证号（密文）
 * @property int|null $id_card_front_id 身份证人像面附件ID
 * @property int|null $id_card_back_id 身份证国徽面附件ID
 * @property int|null $handheld_id 手持身份证附件ID
 * @property string|null $company_name 企业名称
 * @property string|null $credit_code 统一社会信用代码
 * @property string|null $legal_person_name 法人姓名
 * @property string|null $legal_person_id_card_no 法人身份证号（密文）
 * @property int|null $business_license_id 营业执照附件ID
 * @property int|null $opening_permit_id 开户许可证附件ID
 * @property string|null $bank_account 对公账户
 * @property string|null $bank_name 开户行
 * @property int|null $admin_id 审核管理员ID
 * @property string|null $reject_reason 驳回原因（枚举值）
 * @property string|null $reject_remark 驳回备注
 * @property Carbon|null $submitted_at 提交时间
 * @property Carbon|null $reviewed_at 审核时间
 * @property Carbon|null $expires_at 认证有效期
 *
 * 关系对象
 * @property \Illuminate\Database\Eloquent\Model $verifiable 申请人
 * @property Admin|null $admin 审核管理员
 * @property Attachment|null $idCardFront 身份证人像面
 * @property Attachment|null $idCardBack 身份证国徽面
 * @property Attachment|null $handheld 手持身份证
 * @property Attachment|null $businessLicense 营业执照
 * @property Attachment|null $openingPermit 开户许可证
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('identity_verifications')]
#[Fillable([
    'verifiable_type', 'verifiable_id', 'type', 'status',
    'real_name', 'id_card_no', 'id_card_front_id', 'id_card_back_id', 'handheld_id',
    'company_name', 'credit_code', 'legal_person_name', 'legal_person_id_card_no',
    'business_license_id', 'opening_permit_id', 'bank_account', 'bank_name',
    'admin_id', 'reject_reason', 'reject_remark',
    'submitted_at', 'reviewed_at', 'expires_at',
])]
#[Hidden(['id_card_no', 'legal_person_id_card_no', 'bank_account'])]
#[UsePolicy(IdentityVerificationPolicy::class)]
class IdentityVerification extends Model
{
    /** @use HasFactory<IdentityVerificationFactory> */
    use HasFactory;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verifiable_id' => 'integer',
            'type' => IdentityType::class,
            'status' => ReviewStatus::class,
            'id_card_front_id' => 'integer',
            'id_card_back_id' => 'integer',
            'handheld_id' => 'integer',
            'business_license_id' => 'integer',
            'opening_permit_id' => 'integer',
            'admin_id' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 申请人（多态）
     */
    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 审核管理员
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * 身份证人像面
     */
    public function idCardFront(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'id_card_front_id');
    }

    /**
     * 身份证国徽面
     */
    public function idCardBack(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'id_card_back_id');
    }

    /**
     * 手持身份证
     */
    public function handheld(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'handheld_id');
    }

    /**
     * 营业执照
     */
    public function businessLicense(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'business_license_id');
    }

    /**
     * 开户许可证
     */
    public function openingPermit(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'opening_permit_id');
    }
}
