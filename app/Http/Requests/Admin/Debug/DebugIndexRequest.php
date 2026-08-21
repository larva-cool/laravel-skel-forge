<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Admin\Debug;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * 调试面板条目列表请求
 *
 * @property-read string $type 条目类型
 * @property-read string|null $tag 标签，多个以英文逗号分隔
 * @property-read string|null $family_hash 同族哈希
 * @property-read string|null $batch_id 批次ID
 * @property-read int|null $before 游标：仅返回 sequence 小于该值的条目
 * @property-read int|null $take 返回条数
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class DebugIndexRequest extends FormRequest
{
    /**
     * 允许查询的条目类型
     *
     * @var list<string>
     */
    public const array TYPES = [
        EntryType::REQUEST,
        EntryType::COMMAND,
        EntryType::SCHEDULED_TASK,
        EntryType::JOB,
        EntryType::BATCH,
        EntryType::CACHE,
        EntryType::QUERY,
        EntryType::MODEL,
        EntryType::EVENT,
        EntryType::MAIL,
        EntryType::NOTIFICATION,
        EntryType::GATE,
        EntryType::VIEW,
        EntryType::REDIS,
        EntryType::EXCEPTION,
        EntryType::LOG,
        EntryType::DUMP,
        EntryType::CLIENT_REQUEST,
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(self::TYPES)],
            'tag' => ['nullable', 'string', 'max:255'],
            'family_hash' => ['nullable', 'string', 'max:255'],
            'batch_id' => ['nullable', 'uuid'],
            'before' => ['nullable', 'integer', 'min:1'],
            'take' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * 转换为调试记录查询选项
     */
    public function toQueryOptions(): EntryQueryOptions
    {
        return (new EntryQueryOptions)
            ->batchId($this->validated('batch_id'))
            ->beforeSequence($this->validated('before'))
            ->tag($this->validated('tag'))
            ->familyHash($this->validated('family_hash'))
            ->limit((int) ($this->validated('take') ?? 50));
    }
}
