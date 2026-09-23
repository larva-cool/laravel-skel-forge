<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\CommonController;
use App\Models\System\Area;
use App\Models\System\Dict;
use App\Models\System\DictItem;
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

    #[Test]
    #[TestDox('白名单内的反馈类型字典可公开访问，且仅返回 label/value/label_type 字段')]
    public function dict_items_returns_feedback_type_items_for_whitelisted_code(): void
    {
        $dict = Dict::query()->firstOrCreate(
            ['code' => 'feedback_type'],
            ['name' => '反馈类型', 'status' => 1, 'sort' => 0]
        );
        // 清理迁移预置的字典项，便于精确控制测试数据
        $dict->dictItems()->delete();
        DictItem::query()->create([
            'dict_id' => $dict->id, 'label' => 'Bug反馈', 'value' => 'bug',
            'label_type' => 'danger', 'status' => 1, 'sort' => 2,
        ]);
        DictItem::query()->create([
            'dict_id' => $dict->id, 'label' => '功能建议', 'value' => 'feature',
            'label_type' => 'primary', 'status' => 1, 'sort' => 0,
        ]);
        // 禁用的字典项不应返回
        DictItem::query()->create([
            'dict_id' => $dict->id, 'label' => '已禁用', 'value' => 'disabled',
            'label_type' => 'info', 'status' => 0, 'sort' => 9,
        ]);

        $response = $this->getJson(route('api.v1.common.dict_items', ['code' => 'feedback_type']));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.value', 'feature')
            ->assertJsonPath('0.label', '功能建议')
            ->assertJsonPath('0.label_type', 'primary')
            ->assertJsonPath('1.value', 'bug')
            ->assertJsonMissing(['value' => 'disabled']);
    }

    #[Test]
    #[TestDox('字典不存在或被禁用时返回空列表')]
    public function dict_items_returns_empty_when_dict_not_found_or_disabled(): void
    {
        $dict = Dict::query()->firstOrCreate(
            ['code' => 'feedback_type'],
            ['name' => '反馈类型', 'status' => 0, 'sort' => 0]
        );
        $dict->update(['status' => 0]);

        $response = $this->getJson(route('api.v1.common.dict_items', ['code' => 'feedback_type']));

        $response->assertOk()->assertJsonCount(0);
    }
}
