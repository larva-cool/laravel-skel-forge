<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Settings;

use App\Models\System\Setting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 系统配置修改工具。
 *
 * 仅允许修改已存在配置项的 value，按 cast_type 自动转型并持久化，写操作需二次确认。
 */
class SetSetting implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '修改已存在的系统配置项的值。按配置项的 cast_type 自动完成类型转换（int/float/bool/string），写入数据库并清理配置缓存。不能创建新的配置项，也不能修改 key/名称。该操作会影响全局系统行为，执行前会展示旧值与新值并请求二次确认。';
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
                ->required()
                ->max(100)
                ->description('要修改的配置键名，必须已存在，例如 system.name、user.default_points'),
            'value' => $schema->string()
                ->required()
                ->description('新的值，统一以字符串形式传入，服务端会根据配置项的 cast_type 自动转型。布尔值使用 "1"/"0" 或 "true"/"false"'),
            'reason' => $schema->string()
                ->max(200)
                ->description('修改原因，将展示在二次确认信息中'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $setting = Setting::query()->where('key', $request->string('key')->toString())->first();
        if (! $setting) {
            return false;
        }

        $newValue = $request->string('value')->toString();
        $reason = $request->string('reason')->toString();

        $message = "即将修改配置「{$setting->name}」（{$setting->key}）：\n".
            "  - 原值：{$setting->value}\n".
            "  - 新值：{$newValue}\n".
            "  - 类型：{$setting->cast_type}";
        if ($reason !== '') {
            $message .= "\n  - 原因：{$reason}";
        }

        return Approval::required($message);
    }

    /**
     * 执行修改。
     */
    public function handle(Request $request): Stringable|string
    {
        $key = $request->string('key')->toString();

        /** @var Setting|null $setting */
        $setting = Setting::query()->where('key', $key)->first();
        if (! $setting) {
            return "未找到键为「{$key}」的配置项。系统设置工具不支持新建配置项，操作已取消。";
        }

        $rawValue = $request->string('value')->toString();
        $storedValue = $this->normalizeForStorage($rawValue, $setting->cast_type);

        $oldValue = $setting->value;
        $setting->forceFill(['value' => $storedValue])->save();

        // 清理配置缓存，使新值立即生效
        settings()->clearCache();

        return "已成功更新配置「{$setting->name}」（{$setting->key}）。\n".
            "  - 原值：{$oldValue}\n".
            "  - 新值：{$storedValue}\n".
            '  - 配置缓存已清理，新值立即生效。';
    }

    /**
     * 将输入值按 cast_type 归一化为可存储字符串。
     */
    protected function normalizeForStorage(string $value, string $castType): string
    {
        return match ($castType) {
            'int', 'integer' => (string) (int) $value,
            'float' => (string) (float) $value,
            'bool', 'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0',
            default => $value,
        };
    }
}
