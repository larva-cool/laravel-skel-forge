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
            'groups' => $this->sortGroups(array_values($groups)),
            'disks' => $disks,
        ]);
    }

    /**
     * 按预定义顺序对分组重排，未在顺序中的分组追加在末尾并按 key 升序兜底。
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    protected function sortGroups(array $groups): array
    {
        $order = $this->groupOrder();
        $sorted = [];
        $remainder = [];

        foreach ($groups as $group) {
            $key = $group['key'];
            if (array_key_exists($key, $order)) {
                $sorted[$order[$key]] = $group;
            } else {
                $remainder[] = $group;
            }
        }

        ksort($sorted);
        usort($remainder, fn ($a, $b) => strcmp((string) $a['key'], (string) $b['key']));

        return array_merge(array_values($sorted), $remainder);
    }

    /**
     * 配置分组展示顺序（数值小的排在前），未列出分组按 key 升序追加在末尾。
     *
     * @return array<string, int>
     */
    protected function groupOrder(): array
    {
        return [
            'system' => 10,
            'admin' => 20,
            'sms_captcha' => 30,
            'email_captcha' => 40,
            'user' => 50,
            'upload' => 60,
            'wallet' => 70,
            'password' => 80,
            'openai' => 90,
            'broadcast' => 100,
            'schedule' => 200,
        ];
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
        $items = $request->itemsData();

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
     * 分组标题映射（语言包 key 后缀与分组 key 一致）
     *
     * @return array<string, string>
     */
    protected function groupTitles(): array
    {
        return [
            'system' => __('admin.setting_group_system'),
            'admin' => __('admin.setting_group_admin'),
            'sms_captcha' => __('admin.setting_group_sms_captcha'),
            'email_captcha' => __('admin.setting_group_email_captcha'),
            'user' => __('admin.setting_group_user'),
            'upload' => __('admin.setting_group_upload'),
            'schedule' => __('admin.setting_group_schedule'),
            'openai' => __('admin.setting_group_openai'),
            'broadcast' => __('admin.setting_group_broadcast'),
            'wallet' => __('admin.setting_group_wallet'),
            'password' => __('admin.setting_group_password'),
        ];
    }
}
