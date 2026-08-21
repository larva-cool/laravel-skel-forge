<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Audit;

use App\Models\System\MailCode;
use App\Models\System\PhoneCode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 验证码发送记录查询工具。
 *
 * 统一查询短信验证码与邮件验证码的发送记录，支持按账号、状态、时间范围筛选。
 * 为安全起见，不返回验证码明文。只读操作。
 */
class ListVerificationCodes implements Tool
{
    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询短信和邮件验证码的发送记录，支持按渠道（短信/邮件）、接收账号（手机号或邮箱）、使用状态（未使用/已使用）、发送时间范围筛选。用于排查验证码收不到、频繁发送等问题。出于安全考虑不返回验证码明文。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel' => $schema->string()
                ->enum(['sms', 'mail'])
                ->required()
                ->description('验证码渠道：sms=短信验证码，mail=邮件验证码'),
            'account' => $schema->string()
                ->max(100)
                ->description('接收账号，短信为手机号，邮件为邮箱地址。支持模糊匹配'),
            'state' => $schema->string()
                ->enum(['unused', 'used'])
                ->description('使用状态：unused=未使用，used=已使用'),
            'scene' => $schema->string()
                ->max(50)
                ->description('短信验证码场景（仅 channel=sms 时生效），例如 register、login、reset'),
            'sent_after' => $schema->string()
                ->format('date')
                ->description('发送起始日期（YYYY-MM-DD），含当天'),
            'sent_before' => $schema->string()
                ->format('date')
                ->description('发送截止日期（YYYY-MM-DD），含当天'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        $channel = $request->string('channel')->toString();

        $query = $channel === 'sms' ? PhoneCode::query() : MailCode::query();

        if ($account = $request->string('account')->toString()) {
            $query->where($channel === 'sms' ? 'phone' : 'email', 'like', "%{$account}%");
        }

        if ($state = $request->string('state')->toString()) {
            $query->where('state', $state === 'used' ? 1 : 0);
        }

        if ($channel === 'sms' && ($scene = $request->string('scene')->toString())) {
            $query->where('scene', $scene);
        }

        if ($after = $request->string('sent_after')->toString()) {
            $query->whereDate('send_at', '>=', $after);
        }
        if ($before = $request->string('sent_before')->toString()) {
            $query->whereDate('send_at', '<=', $before);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('send_at')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(function ($record) use ($channel) {
            $data = [
                'id' => $record->id,
                'account' => $channel === 'sms' ? $record->phone : $record->email,
                'state' => $record->state === 1 ? 'used' : 'unused',
                'state_label' => $record->state === 1 ? '已使用' : '未使用',
                'verify_count' => $record->verify_count,
                'send_at' => $record->send_at?->toDateTimeString(),
                'usage_at' => $record->usage_at?->toDateTimeString(),
            ];

            if ($channel === 'sms') {
                $data['scene'] = $record->scene;
            }

            return $data;
        });

        return json_encode([
            'channel' => $channel,
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
