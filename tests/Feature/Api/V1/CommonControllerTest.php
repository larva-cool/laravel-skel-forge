<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\CommonController;
use App\Models\System\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * API V1 公共控制器功能测试
 */
#[CoversClass(CommonController::class)]
#[Group('api')]
#[Group('common')]
class CommonControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('未传 area_id 时返回顶级地区，并按 sort、id 升序排列')]
    public function area_returns_root_areas_ordered_by_sort_and_id(): void
    {
        $second = Area::factory()->province()->create(['sort' => 2]);
        $first = Area::factory()->province()->create(['sort' => 1]);
        $child = Area::factory()->city($first->id)->create();

        $response = $this->getJson(route('api.v1.common.area'));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $first->id)
            ->assertJsonPath('1.id', $second->id)
            ->assertJsonMissing(['id' => $child->id]);
    }

    #[Test]
    #[TestDox('传入 area_id 时返回该地区的下级地区')]
    public function area_returns_children_of_given_area(): void
    {
        $province = Area::factory()->province()->create();
        $city = Area::factory()->city($province->id)->create(['sort' => 5]);

        $response = $this->getJson(route('api.v1.common.area', ['area_id' => $province->id]));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $city->id)
            ->assertJsonPath('0.name', $city->name);
    }
}
