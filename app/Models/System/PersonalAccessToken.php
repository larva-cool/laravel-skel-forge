<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Models\Traits\DateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * 个人访问令牌
 *
 * @property int $id ID
 * @property int $tokenable_id ID
 * @property string $tokenable_type 类型
 * @property string $name 名称
 * @property string $token 令牌
 * @property string $abilities 权限
 * @property Carbon $last_used_at 最后使用时间
 * @property Carbon $expires_at 过期时间
 * @property Carbon $created_at 添加时间
 * @property Carbon $updated_at 更新时间
 * @property Model $tokenable 令牌所属模型
 *
 * @codeCoverageIgnore
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use DateTimeFormatter;
}
