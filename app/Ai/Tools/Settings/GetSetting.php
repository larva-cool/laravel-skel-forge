<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Settings;

use App\Models\System\Setting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 系统配置查询工具。
 *
 * 支持按 key 精确查询单个配置、按前缀批量查询，或关键字搜索配置项。
 */
class GetSetting implements Tool
{
    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询系统配置项。支持按 key 精确查询单个配置、按前缀批量查询，或按关键字搜索配置名称/键。返回配置的名称、键、值（按 cast_type 自动转型）、输入类型与说明。只读操作，安全无副作用。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->max(100)
                ->description('精确配置键名，例如 system.name、user.default_points。提供本字段时返回单个配置'),
            'prefix' => $schema->string()
                ->max(100)
                ->description('配置键前缀，例如 user.、sms_captcha.。提供本字段时返回该前缀下所有配置'),
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配配置名称或键名，用于不确定完整 key 时搜索'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('关键字搜索时分页页码'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(100)
                ->default(30)
                ->description('关键字搜索时每页条数，最大 100'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        $key = $request->string('key')->toString();
        $prefix = $request->string('prefix')->toString();
        $keyword = $request->string('keyword')->toString();

        if ($key !== '') {
            return $this->showSingle($key);
        }

        if ($prefix !== '') {
            return $this->showByPrefix($prefix);
        }

        if ($keyword !== '') {
            return $this->search($keyword, $request->integer('page', 1) ?: 1, $request->integer('per_page', 30) ?: 30);
        }

        return '错误：请提供 key、prefix、keyword 三个查询条件中的至少一个。';
    }

    /**
     * 查询单个配置。
     */
    protected function showSingle(string $key): string
    {
        /** @var Setting|null $setting */
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return "未找到键为「{$key}」的配置项。";
        }

        return json_encode($this->formatSetting($setting), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 按前缀批量查询。
     */
    protected function showByPrefix(string $prefix): string
    {
        $settings = Setting::query()
            ->where('key', 'like', $prefix.'%')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($settings->isEmpty()) {
            return "未找到前缀为「{$prefix}」的配置项。";
        }

        return json_encode([
            'prefix' => $prefix,
            'count' => $settings->count(),
            'items' => $settings->map(fn (Setting $s) => $this->formatSetting($s))->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 关键字搜索（分页）。
     */
    protected function search(string $keyword, int $page, int $perPage): string
    {
        $paginator = Setting::query()
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('key', 'like', "%{$keyword}%");
            })
            ->orderBy('sort')
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 100), page: $page);

        return json_encode([
            'data' => $paginator->getCollection()->map(fn (Setting $s) => $this->formatSetting($s))->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 格式化单个配置输出。
     *
     * @return array<string, mixed>
     */
    protected function formatSetting(Setting $setting): array
    {
        return [
            'id' => $setting->id,
            'name' => $setting->name,
            'key' => $setting->key,
            'value' => $this->castValue($setting->value, $setting->cast_type),
            'raw_value' => $setting->value,
            'cast_type' => $setting->cast_type,
            'input_type' => $setting->input_type,
            'remark' => $setting->remark,
            'updated_at' => $setting->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * 根据 cast_type 转换值。
     */
    protected function castValue(?string $value, string $castType): mixed
    {
        return match ($castType) {
            'int', 'integer' => (int) $value,
            'float' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            default => $value,
        };
    }
}
