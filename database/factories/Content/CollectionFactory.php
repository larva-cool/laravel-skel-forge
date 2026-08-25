<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Content;

use App\Models\Content\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * 收藏模型工厂
 *
 * @extends Factory<Collection>
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class CollectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Collection>
     */
    protected $model = Collection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'source_type' => null,
            'source_id' => null,
            'extra' => null,
        ];
    }

    /**
     * 指定收藏目标
     */
    public function forSource(Model $source): static
    {
        return $this->state(fn () => [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
        ]);
    }

    /**
     * 附带扩展信息
     *
     * @param  array<string, mixed>  $extra  扩展信息
     */
    public function withExtra(array $extra): static
    {
        return $this->state(fn () => ['extra' => $extra]);
    }
}
