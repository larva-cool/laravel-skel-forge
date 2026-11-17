<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Http\Request;

/**
 * 认证并检查账号状态
 */
class Authenticate extends BaseAuthenticate
{
    /**
     * 完成 Guard 认证后，拒绝已冻结或禁用的账号。
     *
     * @param  Request  $request
     * @param  array<int, string|null>  $guards
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function authenticate($request, array $guards): void
    {
        parent::authenticate($request, $guards);

        $account = $request->user();
        $isBlocked = ($account instanceof User && $account->status->isFrozen())
            || ($account instanceof Admin && $account->status->isDisabled());

        if (! $isBlocked) {
            return;
        }

        $account->tokens()->delete();
        $this->unauthenticated($request, $guards);
    }
}
