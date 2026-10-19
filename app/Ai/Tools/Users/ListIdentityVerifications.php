<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\User;
use App\Models\User\IdentityVerification;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 实名认证记录查询工具。
 *
 * 支持按关键字、认证类型、审核状态、申请用户、提交时间范围筛选，
 * 返回分页认证记录数据。证件号等敏感字段不返回。只读操作。
 */
class ListIdentityVerifications implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'identity-verifications.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询用户实名认证记录列表。支持按关键字（姓名/企业名称/信用代码/法人姓名/用户昵称或手机号）、认证类型、审核状态、申请用户 ID、提交时间范围筛选，以分页形式返回认证记录（含证件照/营业执照图片地址、驳回原因、审核时间等；证件号码等敏感字段不返回）。只读操作，不能执行审核。';
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
                ->description('模糊匹配关键字，作用于真实姓名、企业名称、统一社会信用代码、法人姓名，以及用户的昵称/手机号'),
            'type' => $schema->string()
                ->enum(['personal', 'enterprise'])
                ->description('认证类型筛选：personal=个人，enterprise=企业'),
            'status' => $schema->string()
                ->enum(['unsubmitted', 'pending', 'approved', 'rejected'])
                ->description('审核状态筛选：unsubmitted=未提交，pending=待审核，approved=已通过，rejected=已驳回'),
            'verifiable_id' => $schema->integer()
                ->description('精确按申请用户 ID 筛选'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('提交起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('提交截止日期（YYYY-MM-DD），含当天'),
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

        $query = IdentityVerification::query()->with('verifiable');

        if ($keyword = $request->string('keyword')->toString()) {
            $like = "%{$keyword}%";
            $query->where(function ($q) use ($like) {
                $q->where('real_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('credit_code', 'like', $like)
                    ->orWhere('legal_person_name', 'like', $like)
                    ->orWhereHasMorph('verifiable', [User::class], function ($uq) use ($like) {
                        $uq->where('name', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
            });
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($verifiableId = $request->integer('verifiable_id')) {
            $query->where('verifiable_id', $verifiableId);
        }

        if ($startDate = $request->string('start_date')->toString()) {
            $query->whereDate('submitted_at', '>=', $startDate);
        }
        if ($endDate = $request->string('end_date')->toString()) {
            $query->whereDate('submitted_at', '<=', $endDate);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (IdentityVerification $verification) => [
            'id' => $verification->id,
            'user' => $verification->verifiable instanceof User ? [
                'id' => $verification->verifiable->id,
                'username' => $verification->verifiable->username,
                'name' => $verification->verifiable->name,
            ] : null,
            'type' => $verification->type->value,
            'type_label' => $verification->type->label(),
            'status' => $verification->status->value,
            'status_label' => $verification->status->label(),
            'real_name' => $verification->real_name,
            'company_name' => $verification->company_name,
            'credit_code' => $verification->credit_code,
            'legal_person_name' => $verification->legal_person_name,
            'bank_name' => $verification->bank_name,
            'id_card_front' => $verification->id_card_front,
            'id_card_back' => $verification->id_card_back,
            'handheld' => $verification->handheld,
            'business_license' => $verification->business_license,
            'opening_permit' => $verification->opening_permit,
            'reject_reason' => $verification->reject_reason,
            'reject_remark' => $verification->reject_remark,
            'submitted_at' => $verification->submitted_at?->toDateTimeString(),
            'reviewed_at' => $verification->reviewed_at?->toDateTimeString(),
            'expires_at' => $verification->expires_at?->toDateTimeString(),
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
