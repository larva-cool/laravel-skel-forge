<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Jobs\User;

use App\Models\System\PersonalAccessToken;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 删除用户访问令牌
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class DeleteAccessTokenJob implements ShouldQueue
{
    use Queueable;

    public string $token;

    /**
     * Create a new job instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        PersonalAccessToken::query()->where('token', $this->token)->delete();
    }
}
