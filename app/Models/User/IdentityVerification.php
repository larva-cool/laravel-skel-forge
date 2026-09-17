<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\User;

use App\Casts\AsAttachmentUrl;
use App\Enums\IdentityType;
use App\Enums\ReviewStatus;
use App\Models\Admin\Admin;
use App\Models\Model;
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
 * @property string|null $id_card_front 身份证人像面图片路径
 * @property string|null $id_card_back 身份证国徽面图片路径
 * @property string|null $handheld 手持身份证图片路径
 * @property string|null $company_name 企业名称
 * @property string|null $credit_code 统一社会信用代码
 * @property string|null $legal_person_name 法人姓名
 * @property string|null $legal_person_id_card_no 法人身份证号（密文）
 * @property string|null $business_license 营业执照图片路径
 * @property string|null $opening_permit 开户许可证图片路径
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
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('identity_verifications')]
#[Fillable([
    'verifiable_type', 'verifiable_id', 'type', 'status',
    'real_name', 'id_card_no', 'id_card_front', 'id_card_back', 'handheld',
    'company_name', 'credit_code', 'legal_person_name', 'legal_person_id_card_no',
    'business_license', 'opening_permit', 'bank_account', 'bank_name',
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
            'id_card_front' => AsAttachmentUrl::class,
            'id_card_back' => AsAttachmentUrl::class,
            'handheld' => AsAttachmentUrl::class,
            'business_license' => AsAttachmentUrl::class,
            'opening_permit' => AsAttachmentUrl::class,
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
}
