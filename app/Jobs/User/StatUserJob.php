<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Jobs\User;

use App\Models\User\UserStat;
use App\Services\UserStatsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * 用户统计任务
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class StatUserJob implements ShouldQueue
{
    use Queueable;

    protected string $statDate;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $statDate = null)
    {
        if (is_null($statDate)) {
            $statDate = Carbon::yesterday()->toDateString();
        }
        $this->statDate = $statDate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (UserStat::query()->where('stat_date', $this->statDate)->exists()) {
            return;
        }

        $stats = app(UserStatsService::class)->calculate(Carbon::parse($this->statDate));

        UserStat::create($stats);
    }
}
