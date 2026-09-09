<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Invite;

use App\Models\Invite\InviteCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * 邀请码模型工厂
 *
 * @extends Factory<InviteCode>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteCodeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<InviteCode>
     */
    protected $model = InviteCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => null,
            'owner_id' => null,
            'owner_type' => null,
        ];
    }

    /**
     * 指定所属主体
     */
    public function forOwner(Model $owner): static
    {
        return $this->state(fn () => [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);
    }
}
