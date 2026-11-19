<?php

namespace App\Console\Commands;

use App\Models\NcbaReceiptClaim;
use App\Services\NcbaReceiptClaimService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileNcbaReceipts extends Command
{
    protected $signature = 'ncba:reconcile-receipts';

    protected $description = 'Recheck open NCBA receipt claims and flag delayed bank confirmations without guessing or moving money';

    public function handle(NcbaReceiptClaimService $service): int
    {
        $failures = 0;
        // Each receipt is reconciled independently, so one damaged record cannot starve others.
        foreach (NcbaReceiptClaim::whereIn('status', [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW])
            ->select('receipt_number')->distinct()->lazyById(100, 'receipt_number') as $claim) {
            try {
                $service->reconcile($claim->receipt_number);
            } catch (\Throwable $exception) {
                $failures++;
                Log::error('NCBA claim reconciliation failed.', ['exception_type' => $exception::class]);
            }
        }
        $this->info($failures ? "Reconciliation completed with {$failures} failures. Check server logs and retry." : 'Open receipt claims reconciled.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
