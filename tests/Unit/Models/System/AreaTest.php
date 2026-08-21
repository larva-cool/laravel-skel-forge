<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Models\System\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Area 模型单元测试
 */
#[CoversClass(Area::class)]
#[Group('models')]
class AreaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试创建地区
     */
    #[Test]
    #[TestDox('创建地区并验证字段')]
    public function create_area_with_valid_fields(): void
    {
        $area = Area::create([
            'name' => '北京市',
            'area_code' => 110000,
            'lat' => 39.9042,
            'lng' => 116.4074,
            'city_code' => '010',
            'sort' => 1,
        ]);

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'name' => '北京市',
            'area_code' => 110000,
        ]);
    }

    /**
     * 测试 sort 默认值为 0
     */
    #[Test]
    #[TestDox('未指定 sort 时使用默认值 0')]
    public function create_area_uses_default_sort(): void
    {
        $area = Area::create([
            'name' => '天津市',
        ]);

        $this->assertSame(0, $area->sort);
    }

    /**
     * 测试 parent 关联返回父地区
     */
    #[Test]
    #[TestDox('parent 关联返回父地区')]
    public function parent_relation_returns_parent_area(): void
    {
        $parent = Area::create(['name' => '广东省']);
        $child = Area::create(['name' => '深圳市', 'parent_id' => $parent->id]);

        $this->assertInstanceOf(Area::class, $child->parent);
        $this->assertSame($parent->id, $child->parent->id);
        $this->assertSame('广东省', $child->parent->name);
    }

    /**
     * 测试 parent 关联无父地区时返回 null
     */
    #[Test]
    #[TestDox('parent 关联无父地区时返回 null')]
    public function parent_relation_returns_null_when_no_parent(): void
    {
        $area = Area::create(['name' => '上海市']);

        $this->assertNull($area->parent);
    }

    /**
     * 测试 children 关联返回子地区并按 sort 和 id 排序
     */
    #[Test]
    #[TestDox('children 关联返回子地区并按 sort、id 排序')]
    public function children_relation_returns_children_ordered(): void
    {
        $parent = Area::create(['name' => '浙江省']);

        Area::create(['name' => '温州市', 'parent_id' => $parent->id, 'sort' => 2]);
        Area::create(['name' => '杭州市', 'parent_id' => $parent->id, 'sort' => 1]);
        Area::create(['name' => '宁波市', 'parent_id' => $parent->id, 'sort' => 1]);

        $children = $parent->children;

        $this->assertCount(3, $children);
        $this->assertSame('杭州市', $children[0]->name);
        $this->assertSame('宁波市', $children[1]->name);
        $this->assertSame('温州市', $children[2]->name);
    }

    /**
     * 测试 children 关联无子地区时返回空集合
     */
    #[Test]
    #[TestDox('children 关联无子地区时返回空集合')]
    public function children_relation_returns_empty_collection_when_no_children(): void
    {
        $area = Area::create(['name' => '海南省']);

        $this->assertTrue($area->children->isEmpty());
    }

    /**
     * 测试 getChildrenIds 返回子地区 ID 数组
     */
    #[Test]
    #[TestDox('getChildrenIds 返回子地区 ID 数组')]
    public function get_children_ids_returns_array_of_child_ids(): void
    {
        $parent = Area::create(['name' => '江苏省']);

        $child1 = Area::create(['name' => '南京市', 'parent_id' => $parent->id]);
        $child2 = Area::create(['name' => '苏州市', 'parent_id' => $parent->id]);

        $ids = $parent->getChildrenIds();

        $this->assertIsArray($ids);
        $this->assertCount(2, $ids);
        $this->assertContains($child1->id, $ids);
        $this->assertContains($child2->id, $ids);
    }

    /**
     * 测试 getChildrenIds 无子地区时返回空数组
     */
    #[Test]
    #[TestDox('getChildrenIds 无子地区时返回空数组')]
    public function get_children_ids_returns_empty_array_when_no_children(): void
    {
        $area = Area::create(['name' => '青海省']);

        $this->assertSame([], $area->getChildrenIds());
    }

    /**
     * 测试 getChildIds 返回逗号分隔的子 ID 字符串
     */
    #[Test]
    #[TestDox('getChildIds 返回逗号分隔的子 ID 字符串')]
    public function get_child_ids_returns_comma_separated_string(): void
    {
        $parent = Area::create(['name' => '四川省']);

        $child1 = Area::create(['name' => '成都市', 'parent_id' => $parent->id]);
        $child2 = Area::create(['name' => '绵阳市', 'parent_id' => $parent->id]);

        $result = Area::getChildIds($parent->id);

        $this->assertIsString($result);
        $ids = explode(',', $result);
        $this->assertCount(2, $ids);
        $this->assertContains((string) $child1->id, $ids);
        $this->assertContains((string) $child2->id, $ids);
    }

    /**
     * 测试 getChildIds 无子地区时返回空字符串
     */
    #[Test]
    #[TestDox('getChildIds 无子地区时返回空字符串')]
    public function get_child_ids_returns_empty_string_when_no_children(): void
    {
        $parent = Area::create(['name' => '西藏自治区']);

        $this->assertSame('', Area::getChildIds($parent->id));
    }

    /**
     * 测试字段类型转换
     */
    #[Test]
    #[TestDox('字段类型转换正确')]
    public function casts_attributes_correctly(): void
    {
        $area = Area::create([
            'name' => '重庆市',
            'area_code' => '500000',
            'lat' => '29.5630',
            'lng' => '106.5516',
            'sort' => '5',
        ]);

        $this->assertIsInt($area->id);
        $this->assertIsInt($area->area_code);
        $this->assertIsFloat($area->lat);
        $this->assertIsFloat($area->lng);
        $this->assertIsInt($area->sort);
    }
}
