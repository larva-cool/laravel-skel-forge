<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\System\Area;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

/**
 * 从民政部导入省、市、区县三级行政区划数据。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Signature('areas:import')]
#[Description('从民政部导入省、市、区县三级行政区划数据（按行政区划代码更新，不删除本地数据）')]
class ImportAreasCommand extends Command
{
    private const string SOURCE_URL = 'https://dmfw.mca.gov.cn/9095/xzqh/getList?code=00&maxLevel=3';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $areas = Http::withHeaders([
                'Accept' => 'application/json, text/plain, */*',
                'Referer' => 'https://dmfw.mca.gov.cn/',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            ])
                ->connectTimeout(5)
                ->timeout(30)
                ->retry([200, 500, 1000])
                ->get(self::SOURCE_URL)
                ->throw()
                ->json('data.children');

            if (! is_array($areas) || $areas === []) {
                throw new UnexpectedValueException('接口未返回行政区划数据。');
            }

            $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];

            DB::transaction(function () use ($areas, &$stats): void {
                foreach ($areas as $area) {
                    $this->importArea($area, null, $stats);
                }
            });
        } catch (Throwable $exception) {
            $this->error("导入失败：{$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            '导入完成：新增 %d，更新 %d，无变化 %d，跳过 %d。',
            $stats['created'],
            $stats['updated'],
            $stats['unchanged'],
            $stats['skipped']
        ));

        return self::SUCCESS;
    }

    /**
     * 递归写入地区及其子地区。
     *
     * @param  array<string, mixed>  $area
     * @param  array{created: int, updated: int, unchanged: int, skipped: int}  $stats
     */
    private function importArea(array $area, ?int $parentId, array &$stats): void
    {
        $sourceCode = (string) ($area['code'] ?? '');
        $name = trim((string) ($area['name'] ?? ''));
        $children = $area['children'] ?? [];

        if ($sourceCode === '资料暂缺') {
            $stats['skipped']++;

            return;
        }

        if (preg_match('/^\d{12}$/', $sourceCode) !== 1 || $name === '' || ! is_array($children)) {
            throw new UnexpectedValueException("接口返回了无效的行政区划节点：{$sourceCode}");
        }

        $areaModel = Area::query()->firstOrNew([
            'area_code' => (int) substr($sourceCode, 0, 6),
        ]);
        $areaModel->fill([
            'parent_id' => $parentId,
            'name' => $name,
        ]);

        $hasChanges = $areaModel->isDirty();
        $areaModel->save();

        if ($areaModel->wasRecentlyCreated) {
            $stats['created']++;
        } elseif ($hasChanges) {
            $stats['updated']++;
        } else {
            $stats['unchanged']++;
        }

        foreach ($children as $child) {
            $this->importArea($child, $areaModel->id, $stats);
        }
    }
}
