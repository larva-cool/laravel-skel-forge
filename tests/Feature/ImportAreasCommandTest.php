<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ImportAreasCommand;
use App\Models\System\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 行政区划导入命令测试
 */
#[CoversClass(ImportAreasCommand::class)]
#[Group('areas')]
class ImportAreasCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('导入行政区划树并在重复执行时保持数据幂等')]
    public function command_imports_area_tree_idempotently(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dmfw.mca.gov.cn/9095/xzqh/getList*' => Http::response([
                'data' => [
                    'children' => [
                        [
                            'code' => '110000000000',
                            'name' => '北京市',
                            'children' => [
                                ['code' => '110101000000', 'name' => '东城区', 'children' => []],
                            ],
                        ],
                        ['code' => '资料暂缺', 'name' => '台湾省', 'children' => []],
                        [
                            'code' => '130000000000',
                            'name' => '河北省',
                            'children' => [
                                [
                                    'code' => '130100000000',
                                    'name' => '石家庄市',
                                    'children' => [
                                        ['code' => '130102000000', 'name' => '长安区', 'children' => []],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->assertSame(0, Artisan::call('areas:import'));
        $this->assertStringContainsString('导入完成：新增 5，更新 0，无变化 0，跳过 1。', Artisan::output());

        $province = Area::query()->where('area_code', 130000)->firstOrFail();
        $city = Area::query()->where('area_code', 130100)->firstOrFail();
        $district = Area::query()->where('area_code', 130102)->firstOrFail();
        $municipalityDistrict = Area::query()->where('area_code', 110101)->firstOrFail();

        $this->assertSame($province->id, $city->parent_id);
        $this->assertSame($city->id, $district->parent_id);
        $this->assertSame(Area::query()->where('area_code', 110000)->value('id'), $municipalityDistrict->parent_id);
        $this->assertSame(5, Area::query()->count());

        $this->assertSame(0, Artisan::call('areas:import'));
        $this->assertStringContainsString('导入完成：新增 0，更新 0，无变化 5，跳过 1。', Artisan::output());

        $this->assertSame(5, Area::query()->count());
    }

    #[Test]
    #[TestDox('接口节点无效时导入失败并回滚已写入数据')]
    public function command_rolls_back_when_response_contains_invalid_area(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dmfw.mca.gov.cn/9095/xzqh/getList*' => Http::response([
                'data' => [
                    'children' => [
                        ['code' => '110000000000', 'name' => '北京市', 'children' => []],
                        ['code' => 'invalid', 'name' => '无效地区', 'children' => []],
                    ],
                ],
            ]),
        ]);

        $this->assertSame(1, Artisan::call('areas:import'));
        $this->assertStringContainsString('导入失败', Artisan::output());

        $this->assertSame(0, Area::query()->count());
    }
}
