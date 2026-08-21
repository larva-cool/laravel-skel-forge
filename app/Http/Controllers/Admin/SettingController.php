<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SettingInputType;
use App\Http\Requests\Admin\Setting\SettingSaveRequest;
use App\Http\Requests\Admin\Setting\StoreConfigRequest;
use App\Http\Resources\Admin\SettingResource;
use App\Models\System\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * 后台配置管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SettingController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:settings.index')->only(['index', 'show', 'groups', 'inputTypes']);
        $this->middleware('permission:settings.create')->only(['store']);
        $this->middleware('permission:settings.edit')->only(['update', 'batchUpdate']);
        $this->middleware('permission:settings.delete')->only(['destroy']);
    }

    /**
     * 配置列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = Setting::query();

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('key', 'like', "%{$keyword}%");
            });
        }

        if ($castType = $request->query('cast_type')) {
            $query->where('cast_type', $castType);
        }

        $items = $query->orderBy('sort')->orderByDesc('id')->paginate($perPage);

        return SettingResource::collection($items);
    }

    /**
     * 创建配置
     */
    public function store(SettingSaveRequest $request): JsonResponse
    {
        $setting = Setting::create($request->validated());
        settings()->clearCache();

        return response()->json(new SettingResource($setting), 201);
    }

    /**
     * 获取配置详情
     */
    public function show(int $id): SettingResource
    {
        return new SettingResource(Setting::findOrFail($id));
    }

    /**
     * 更新配置
     */
    public function update(SettingSaveRequest $request, int $id): SettingResource
    {
        $setting = Setting::findOrFail($id);
        $setting->update($request->validated());
        settings()->clearCache();

        return new SettingResource($setting);
    }

    /**
     * 删除配置
     */
    public function destroy(int $id): JsonResponse
    {
        $setting = Setting::findOrFail($id);
        $setting->delete();
        settings()->clearCache();

        return response()->json(status: 204);
    }

    /**
     * 获取分组配置（用于设置页面表单渲染）
     *
     * 返回按 key 第一段分组的配置项，包含 name、key、value、cast_type、input_type、param、remark 等元数据
     */
    public function groups(): JsonResponse
    {
        $settings = Setting::query()->orderBy('sort')->orderBy('id')->get();
        $disks = array_keys(config('filesystems.disks'));

        // 按 key 前缀（第一段）分组
        $groups = [];
        foreach ($settings as $setting) {
            $tag = explode('.', $setting->key)[0] ?? 'default';

            $param = null;
            if ($setting->param) {
                $param = json_decode($setting->param, true);
            }

            $value = $setting->value;
            // 按 cast_type 做类型转换
            $value = match ($setting->cast_type) {
                'int', 'integer' => (int) $value,
                'float' => (float) $value,
                'bool', 'boolean' => (bool) $value,
                default => $value,
            };

            // 字段名（key 去掉分组前缀）
            $field = substr($setting->key, strlen($tag) + 1);

            if (! isset($groups[$tag])) {
                $groups[$tag] = [
                    'key' => $tag,
                    'title' => $this->groupTitles()[$tag] ?? ucfirst($tag),
                    'items' => [],
                ];
            }

            $groups[$tag]['items'][] = [
                'name' => $setting->name,
                'key' => $setting->key,
                'field' => $field,
                'value' => $value,
                'cast_type' => $setting->cast_type,
                'input_type' => $setting->input_type,
                'param' => $param,
                'remark' => $setting->remark,
                'sort' => $setting->sort,
            ];
        }

        return response()->json([
            'groups' => array_values($groups),
            'disks' => $disks,
        ]);
    }

    /**
     * 获取所有可用的配置输入类型（供前端下拉选择使用）
     */
    public function inputTypes(): JsonResponse
    {
        $data = array_map(
            fn (SettingInputType $case): array => ['value' => $case->value, 'label' => $case->label()],
            SettingInputType::cases()
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 批量保存配置
     */
    public function batchUpdate(StoreConfigRequest $request): JsonResponse
    {
        $data = $request->validated();
        $castTypes = settings()->castTypes();
        $updateTime = Carbon::now();

        $items = [];
        foreach ($data as $key => $val) {
            // 将布尔值转为字符串存储
            $castType = $castTypes[$key] ?? 'string';
            if ($castType === 'bool' || $castType === 'boolean') {
                $val = $val ? '1' : '0';
            } elseif (is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
            $items[] = ['key' => $key, 'value' => (string) $val, 'updated_at' => $updateTime];
        }

        // 没有任何可写入的配置项时明确报错，避免静默失败让前端误以为保存成功
        if (empty($items)) {
            return response()->json(['message' => __('admin.setting_save_empty')], 422);
        }

        Setting::query()->upsert(
            $items,
            ['key'],
            ['value', 'updated_at'],
        );

        settings()->clearCache();
        settings()->all(true);

        return response()->json(['message' => __('admin.setting_save_success')]);
    }

    /**
     * 分组中文标题映射
     *
     * @return array<string, string>
     */
    protected function groupTitles(): array
    {
        return [
            'system' => '基本信息',
            'sms_captcha' => '短信设置',
            'email_captcha' => '邮件设置',
            'user' => '会员设置',
            'upload' => '上传设置',
            'schedule' => '调度设置',
            'openai' => 'OpenAI 配置',
            'broadcast' => '点播设置',
        ];
    }
}
