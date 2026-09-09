<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Invite;

use App\Models\Invite\InviteRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * 邀请记录模型工厂
 *
 * @extends Factory<InviteRecord>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InviteRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<InviteRecord>
     */
    protected $model = InviteRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inviter_id' => null,
            'inviter_type' => null,
            'invitee_id' => null,
            'invitee_type' => null,
            'description' => null,
        ];
    }

    /**
     * 指定邀请人
     */
    public function forInviter(Model $inviter): static
    {
        return $this->state(fn () => [
            'inviter_type' => $inviter->getMorphClass(),
            'inviter_id' => $inviter->getKey(),
        ]);
    }

    /**
     * 指定被邀请人
     */
    public function forInvitee(Model $invitee): static
    {
        return $this->state(fn () => [
            'invitee_type' => $invitee->getMorphClass(),
            'invitee_id' => $invitee->getKey(),
        ]);
    }

    /**
     * 设置描述
     */
    public function withDescription(string $description): static
    {
        return $this->state(fn () => ['description' => $description]);
    }
}
