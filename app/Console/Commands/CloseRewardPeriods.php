<?php

namespace App\Console\Commands;

use App\Services\RewardPeriodService;
use Illuminate\Console\Command;

class CloseRewardPeriods extends Command
{
    protected $signature = 'rewards:close-periods';

    protected $description = 'Calculate and lock ended performance reward periods after their grace window';

    public function handle(RewardPeriodService $rewards): int
    {
        $closed = $rewards->closeEndedPeriods();
        $this->info("Closed {$closed} reward period(s).");

        return self::SUCCESS;
    }
}
