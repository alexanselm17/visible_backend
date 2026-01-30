<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReferralRewardService
{
    const REWARD_AMOUNT = 30;

    public static function handle(string $referredUserId): void
    {
        // 1️⃣ Get referred user
        $referredUser = User::find($referredUserId);

        if (!$referredUser || empty($referredUser->referal_code)) {
            return; // no referral
        }

        // 2️⃣ Get referrer
        $referrer = User::where('my_code', $referredUser->referal_code)->first();

        if (!$referrer) {
            return;
        }

        // 3️⃣ Check screenshot condition (≥2 same advert)
        $qualifies = DB::table('screenshots')
            ->where('processed_by', $referredUser->id)
            ->groupBy('advert_id')
            ->havingRaw('COUNT(*) >= 2')
            ->exists();

        if (!$qualifies) {
            return;
        }

        // 4️⃣ Prevent double payment
        $alreadyPaid = Invoice::where('type', 'Referal')
            ->where('processed_by', $referrer->id)
            ->where('posted_by', $referredUser->id)
            ->exists();

        if ($alreadyPaid) {
            return;
        }

        // 5️⃣ Get last balance
        $lastInvoice = Invoice::where('processed_by', $referrer->id)->latest()->first();
        $balance = $lastInvoice?->customer_balance ?? 0;

        // 6️⃣ Pay referral
        Invoice::create([
            'type' => 'Referal',
            'amount' => self::REWARD_AMOUNT,
            'processed_by' => $referrer->id,
            'posted_by' => $referredUser->id,
            'customer_balance' => $balance + self::REWARD_AMOUNT,
        ]);
    }
}
