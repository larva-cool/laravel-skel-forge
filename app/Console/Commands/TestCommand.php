<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\Assistant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:test')]
#[Description('Command description')]
class TestCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $response = (new Assistant)->prompt(prompt: '当前的数据表是哪个？',
            model: 'ep-20260809190412-bkx2g',
            provider: 'volc');

        $this->info($response->raw);

        // $response->raw; // Illuminate\Http\Client\Response|null

        // $response->raw->header('X-RateLimit-Remaining-Requests');
        // $response->raw->json('id');
    }
}
