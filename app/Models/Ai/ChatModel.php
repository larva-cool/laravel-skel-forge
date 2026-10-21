<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Ai;

use App\Models\Model;
use Database\Factories\Ai\ChatModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 聊天页可选 AI 模型
 *
 * @property int $id 主键
 * @property string $provider AI 服务商
 * @property string $model_id 模型 ID（服务商侧标识）
 * @property string $name 展示名称
 * @property bool $is_default 是否默认模型
 * @property bool $is_enabled 是否启用
 * @property int $sort 排序（越小越靠前）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('chat_models')]
#[Fillable(['provider', 'model_id', 'name', 'is_default', 'is_enabled', 'sort'])]
class ChatModel extends Model
{
    /** @use HasFactory<ChatModelFactory> */
    use HasFactory;

    /**
     * 模型事件：保证默认模型全局唯一
     */
    protected static function booted(): void
    {
        static::saving(function (ChatModel $model) {
            if ($model->is_default && $model->isDirty('is_default')) {
                static::query()
                    ->whereKeyNot($model->getKey() ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'provider' => 'string',
            'model_id' => 'string',
            'name' => 'string',
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * 仅启用并按排序
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true)->orderBy('sort')->orderBy('id');
    }

    /**
     * 聊天页可选模型列表（[{id, name}] 结构）
     *
     * @return \Illuminate\Support\Collection<int, array{id: string, name: string}>
     */
    public static function options(): \Illuminate\Support\Collection
    {
        return static::query()
            ->enabled()
            ->get(['model_id', 'name'])
            ->map(fn (ChatModel $model) => ['id' => $model->model_id, 'name' => $model->name]);
    }

    /**
     * 默认模型 ID（无默认时回退到首个启用模型，最终回退到配置）
     */
    public static function defaultModelId(): ?string
    {
        return static::query()
            ->enabled()
            ->where('is_default', true)
            ->value('model_id')
            ?? static::query()->enabled()->value('model_id')
            ?? config('ai.providers.volc.models.text.default');
    }

    /**
     * 可用模型 ID 列表（用于请求参数校验）
     *
     * @return array<int, string>
     */
    public static function availableIds(): array
    {
        return static::query()->enabled()->pluck('model_id')->all();
    }
}
