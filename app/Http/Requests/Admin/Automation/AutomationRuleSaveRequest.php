<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Automation;

use App\Enums\AutomationChannel;
use App\Enums\AutomationRewardType;
use App\Enums\AutomationTriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * 自动化规则保存请求（创建/编辑）
 *
 * @property-read string $name 规则名称
 * @property-read AutomationTriggerType $trigger_type 触发类型
 * @property-read array<string, mixed> $trigger_config 触发配置
 * @property-read int|null $segment_id 目标分群 ID
 * @property-read string $title 通知标题模板
 * @property-read string $content 通知内容模板
 * @property-read array<int, string> $channels 触达渠道
 * @property-read AutomationRewardType $reward_type 奖励类型
 * @property-read int $reward_amount 奖励数量
 * @property-read bool $status 启用状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationRuleSaveRequest extends FormRequest
{
    /**
     * 注入创建人管理员 ID，使控制器可直接用 validated() 写入模型。
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['admin_id' => $this->user('admin')?->id]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'trigger_type' => ['required', 'string', new Enum(AutomationTriggerType::class)],
            'trigger_config' => ['required', 'array'],
            'trigger_config.advance_days' => ['required', 'integer', 'min:0', 'max:30'],
            // 调度器每小时第 10 分钟执行，23:10 之后的时间点无法当天触达
            'trigger_config.send_time' => ['required', 'date_format:H:i', 'before_or_equal:23:00'],
            'trigger_config.festival_ids' => ['sometimes', 'array'],
            'trigger_config.festival_ids.*' => ['integer', Rule::exists('automation_festivals', 'id')],
            'segment_id' => ['sometimes', 'nullable', 'integer', Rule::exists('user_segments', 'id')],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:1000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in(array_column(AutomationChannel::cases(), 'value'))],
            'reward_type' => ['required', 'string', new Enum(AutomationRewardType::class)],
            'reward_amount' => [
                'required',
                'integer',
                'min:0',
                'max:100000',
                Rule::when(
                    $this->input('reward_type') !== AutomationRewardType::NONE->value,
                    ['min:1']
                ),
            ],
            'status' => ['sometimes', 'boolean'],
            'admin_id' => ['nullable', 'integer'],
        ];
    }
}
