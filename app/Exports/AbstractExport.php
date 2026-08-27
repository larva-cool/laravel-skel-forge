<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * 后台数据导出基类
 *
 * 子类只需实现：
 *   - columns(): array                 表头与列定义 ['id' => 'ID', 'username' => '账号']
 *   - buildQuery(): Builder            数据查询
 *   - sheetTitle(): string             Sheet 名
 *
 * 字段类型识别：
 *   - 字段名以 _at 结尾或等于 login_at：自动按 Y-m-d H:i:s 格式化
 *   - 字段名以 _count 结尾或为 id/user_id/admin_id/status/port/operator_id/status_code/runtime：按整数输出
 *
 * 同步导出：`(new XxxExport())->download('xxx.xlsx')`
 * 异步导出：`(new XxxExport())->queue('exports/xxx.xlsx')->allOnQueue('exports')`
 */
abstract class AbstractExport implements FromQuery, WithColumnFormatting, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    /**
     * 列定义 [字段名 => 表头]
     *
     * @return array<string, string>
     */
    abstract protected function columns(): array;

    /**
     * 数据查询（钩子方法，命名避开 FromQuery::query() 接口约束）
     */
    abstract protected function buildQuery(): Builder;

    /**
     * Sheet 名（钩子方法，命名避开 WithTitle::title() 接口约束）
     */
    abstract protected function sheetTitle(): string;

    /**
     * 行字段映射。子类可覆写以自定义转换。
     *
     * @param  object  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        $result = [];
        foreach (array_keys($this->columns()) as $field) {
            $result[] = $this->normalize(data_get($row, $field), $field);
        }

        return $result;
    }

    /**
     * 默认表头
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_values($this->columns());
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle(), 0, 31); // Excel sheet 名最大 31 字符
    }

    public function query(): Builder
    {
        $q = $this->buildQuery();

        // 强制 ORDER BY 主键作为 tie-breaker，避免 FromQuery chunk 漏行/重复
        $orders = $q->getQuery()->orders;
        $primaryKey = $q->getModel()->getKeyName();
        if (is_string($primaryKey)) {
            $hasTieBreaker = false;
            foreach ($orders ?? [] as $order) {
                if (($order['column'] ?? null) === $primaryKey) {
                    $hasTieBreaker = true;
                    break;
                }
            }
            if (! $hasTieBreaker) {
                $q->orderBy($primaryKey);
            }
        }

        return $q;
    }

    /**
     * 列格式化（日期/数字）
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $formats = [];
        $i = 0;
        foreach (array_keys($this->columns()) as $field) {
            $col = $this->columnLetter($i);
            if ($this->isDateField($field)) {
                $formats["{$col}1:{$col}1048576"] = NumberFormat::FORMAT_DATE_DATETIME;
            } elseif ($this->isIntegerField($field)) {
                $formats["{$col}1:{$col}1048576"] = NumberFormat::FORMAT_NUMBER;
            }
            $i++;
        }

        return $formats;
    }

    /**
     * 标准化字段值
     */
    protected function normalize(mixed $value, string $field): string|int|float|null
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_int($value) && $this->isDateField($field) && $value > 0 && $value < 2147483647) {
            try {
                return Date::excelToDateTimeObject($value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return $value;
            }
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($value)) {
            return $value ? '是' : '否';
        }

        return $value;
    }

    /**
     * 字段是否为日期类型
     */
    protected function isDateField(string $field): bool
    {
        return str_ends_with($field, '_at') || $field === 'login_at';
    }

    /**
     * 字段是否为整数类型
     */
    protected function isIntegerField(string $field): bool
    {
        return str_ends_with($field, '_count') || str_starts_with($field, 'is_')
            || in_array($field, ['id', 'user_id', 'admin_id', 'status', 'port', 'operator_id', 'status_code', 'runtime'], true);
    }

    /**
     * 列号转字母（A, B, ..., Z, AA, AB, ...）
     */
    protected function columnLetter(int $index): string
    {
        $letter = '';
        $index += 1;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
