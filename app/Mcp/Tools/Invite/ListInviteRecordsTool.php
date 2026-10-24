<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Invite;

use App\Http\Resources\Api\V1\InviteRecordResource;
use App\Mcp\Tools\Concerns\BuildsPaginatedResponse;
use App\Models\Invite\InviteRecord;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取当前登录用户的邀请记录
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('invite.record.list')]
#[Description('获取当前登录用户作为邀请人的邀请记录（分页），含被邀请人信息。')]
#[IsReadOnly]
#[IsIdempotent]
class ListInviteRecordsTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate($this->paginationRules());

        /** @var \App\Models\User $user */
        $user = $request->user();

        $paginator = InviteRecord::query()
            ->where('inviter_id', $user->getKey())
            ->where('inviter_type', $user->getMorphClass())
            ->with('invitee')
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, InviteRecordResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->paginationSchema($schema);
    }
}
