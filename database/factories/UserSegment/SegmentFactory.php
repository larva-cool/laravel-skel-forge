<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\UserSegment;

use App\Enums\SegmentType;
use App\Models\UserSegment\Segment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 用户分群工厂
 *
 * @extends Factory<Segment>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Segment>
     */
    protected $model = Segment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => null,
            'name' => '分群-'.fake()->unique()->word(),
            'label_type' => fake()->randomElement(['default', 'primary', 'success', 'warning', 'danger', 'info']),
            'type' => SegmentType::MANUAL,
            'group' => fake()->randomElement(['用户画像', '运营分群', '风控', null]),
            'description' => fake()->sentence(),
            'sort' => fake()->numberBetween(0, 100),
            'status' => true,
            'rule' => null,
            'last_synced_at' => null,
            'last_synced_user_count' => null,
        ];
    }

    /**
     * 手动分群（默认即为手动）
     */
    public function manual(): static
    {
        return $this->state(fn () => [
            'type' => SegmentType::MANUAL,
            'code' => null,
            'rule' => null,
        ]);
    }

    /**
     * 规则分群（自动同步）
     */
    public function rule(): static
    {
        $rule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'status', 'op' => '=', 'value' => 1],
            ],
        ];

        return $this->state(fn () => [
            'type' => SegmentType::RULE,
            'code' => 'rule_'.fake()->unique()->word(),
            'rule' => $rule,
        ]);
    }

    /**
     * 禁用状态
     */
    public function disabled(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}
