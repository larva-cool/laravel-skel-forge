<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\UserSegment;

use App\Models\User;
use App\Models\UserSegment\Membership;
use App\Models\UserSegment\Segment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 用户分群成员工厂
 *
 * @extends Factory<Membership>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MembershipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Membership>
     */
    protected $model = Membership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'segment_id' => Segment::factory(),
            'user_id' => User::factory(),
            'source' => Membership::SOURCE_MANUAL,
        ];
    }

    /**
     * 规则来源
     */
    public function fromRule(): static
    {
        return $this->state(fn () => ['source' => Membership::SOURCE_RULE]);
    }
}
