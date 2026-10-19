<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Open;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\UserStatus;
use App\Models\Open\OpenAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 开放平台账户列表查询工具。
 *
 * 支持按关键字、账户状态、实名认证状态筛选，返回分页账户摘要数据。只读操作。
 */
class ListOpenAccounts implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'open_accounts.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询开放平台账户（外部合作方）列表。支持按关键字（名称/用户名/邮箱/手机号）、账户状态（正常/已冻结）、实名认证状态筛选，以分页形式返回账户摘要信息（含速率限制、余额、有效令牌数、实名状态、最近登录等）。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配名称、用户名、邮箱或手机号'),
            'status' => $schema->string()
                ->enum(['active', 'frozen'])
                ->description('账户状态筛选：active=正常，frozen=已冻结'),
            'identity_status' => $schema->string()
                ->enum(['unsubmitted', 'pending', 'approved', 'rejected'])
                ->description('实名认证状态筛选：unsubmitted=未提交，pending=待审核，approved=已通过，rejected=已驳回'),
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
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $query = OpenAccount::query()->withCount('tokens');

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('username', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status === 'frozen' ? UserStatus::FROZEN : UserStatus::ACTIVE);
        }

        if ($identityStatus = $request->string('identity_status')->toString()) {
            $query->where('identity_status', $identityStatus);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (OpenAccount $account) => [
            'id' => $account->id,
            'name' => $account->name,
            'username' => $account->username,
            'email' => $account->email,
            'phone' => $account->phone,
            'contact_name' => $account->contact_name,
            'rate_limit' => $account->rate_limit,
            'balance' => $account->balance,
            'frozen_balance' => $account->frozen_balance,
            'status' => $account->status->value,
            'status_label' => $account->status->label(),
            'identity_status' => $account->identity_status->value,
            'identity_status_label' => $account->identity_status->label(),
            'tokens_count' => $account->tokens_count,
            'login_count' => $account->login_count,
            'last_login_at' => $account->last_login_at?->toDateTimeString(),
            'created_at' => $account->created_at?->toDateTimeString(),
        ]);

        return json_encode([
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
