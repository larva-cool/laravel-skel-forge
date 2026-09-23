<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Setting;

use App\Models\System\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * 批量保存配置请求
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class StoreConfigRequest extends FormRequest
{
    /**
     * 将请求数据统一整理为「扁平点号 key」形式，并剔除数据库中不存在的配置项
     *
     * 前端提交的是扁平点号 key（如 system.title），也兼容嵌套数组（如 system => [title => ...]）。
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        $data = [];
        foreach (Setting::query()->pluck('key') as $key) {
            // Arr::has/Arr::get 会先按完整 key 精确匹配，再按点号做嵌套查找，因此两种格式均可命中
            if (Arr::has($input, $key)) {
                $data[$key] = Arr::get($input, $key);
            }
        }

        $this->replace($data);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // 从数据库获取所有已知配置项的 key 和 cast_type
        $settingKeys = Setting::query()->pluck('cast_type', 'key')->toArray();

        $rules = [];
        foreach ($settingKeys as $key => $castType) {
            // 转义点号，避免 Laravel 将 system.title 当作嵌套路径 $data['system']['title']
            $ruleKey = str_replace('.', '\.', $key);
            $rules[$ruleKey] = match ($castType) {
                'int', 'integer' => ['nullable', 'integer'],
                'float' => ['nullable', 'numeric'],
                'bool', 'boolean' => ['nullable', 'in:0,1,true,false,on,off'],
                default => ['nullable', 'string'],
            };
        }

        return $rules;
    }

    /**
     * 转换为待写入数据库的批量项：
     * - bool: 归一为 '1' / '0'
     * - array: json_encode 为字符串
     * - 其他: (string) 统一为字符串
     *
     * @return array<int, array{key: string, value: string, updated_at: Carbon}>
     */
    public function itemsData(): array
    {
        $data = $this->validated();
        $castTypes = settings()->castTypes();
        $updatedAt = Carbon::now();

        $items = [];
        foreach ($data as $key => $val) {
            $castType = $castTypes[$key] ?? 'string';

            if ($castType === 'bool' || $castType === 'boolean') {
                $val = $val ? '1' : '0';
            } elseif (is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            }

            $items[] = ['key' => $key, 'value' => (string) $val, 'updated_at' => $updatedAt];
        }

        return $items;
    }
}
