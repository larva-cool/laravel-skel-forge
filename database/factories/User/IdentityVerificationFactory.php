<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\User;

use App\Enums\IdentityType;
use App\Enums\ReviewStatus;
use App\Models\User\IdentityVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 实名认证工厂
 *
 * @extends Factory<IdentityVerification>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class IdentityVerificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<IdentityVerification>
     */
    protected $model = IdentityVerification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'verifiable_type' => \App\Models\User::class,
            'verifiable_id' => \App\Models\User::factory(),
            'type' => IdentityType::PERSONAL,
            'status' => ReviewStatus::PENDING,
            'real_name' => fake()->name(),
            'id_card_no' => null,
            'id_card_front' => null,
            'id_card_back' => null,
            'handheld' => null,
            'company_name' => null,
            'credit_code' => null,
            'legal_person_name' => null,
            'legal_person_id_card_no' => null,
            'business_license' => null,
            'opening_permit' => null,
            'bank_account' => null,
            'bank_name' => null,
            'admin_id' => null,
            'reject_reason' => null,
            'reject_remark' => null,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * 个人认证
     */
    public function personal(): static
    {
        return $this->state(fn () => [
            'type' => IdentityType::PERSONAL,
            'real_name' => fake()->name(),
            'id_card_no' => 'enc:'.bin2hex(random_bytes(16)),
        ]);
    }

    /**
     * 企业认证
     */
    public function enterprise(): static
    {
        return $this->state(fn () => [
            'type' => IdentityType::ENTERPRISE,
            'real_name' => null,
            'id_card_no' => null,
            'company_name' => fake()->company(),
            'credit_code' => '91'.fake()->unique()->numerify('################'),
            'legal_person_name' => fake()->name(),
            'legal_person_id_card_no' => 'enc:'.bin2hex(random_bytes(16)),
            'bank_account' => fake()->numerify('####################'),
            'bank_name' => fake()->randomElement(['中国工商银行', '中国建设银行', '中国农业银行', '招商银行']),
        ]);
    }

    /**
     * 待审核
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::PENDING,
        ]);
    }

    /**
     * 已通过
     */
    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::APPROVED,
            'reviewed_at' => now(),
            'expires_at' => now()->addYears(3),
        ]);
    }

    /**
     * 已驳回
     */
    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::REJECTED,
            'reject_reason' => 'mismatch',
            'reject_remark' => '证件信息与提交信息不符',
            'reviewed_at' => now(),
        ]);
    }
}
