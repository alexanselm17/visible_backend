<?php

namespace App\Services;

use App\Models\NcbaPayment;
use App\Models\NcbaReceiptClaim;
use App\Models\TokenPurchase;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\PaymentAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NcbaReceiptClaimService
{
    public static function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[\s()-]+/', '', $phone ?? '');
        if (preg_match('/^(?:\+?254|0)?([17][0-9]{8})$/D', $phone, $matches)) {
            return '254'.$matches[1];
        }

        // Do not guess how the bank hashes/redacts phone numbers.
        return null;
    }

    public function submit(User $actor, string $purchaseId, string $receipt): NcbaReceiptClaim
    {
        $receipt = strtoupper(trim($receipt));
        validator(['receipt_number' => $receipt], ['receipt_number' => ['required', 'string', 'regex:/^[A-Z0-9]{1,32}$/D']])->validate();
        // No bank receipt lookup or existence disclosure before the ownership check.
        TokenPurchase::where('user_id', $actor->id)->findOrFail($purchaseId);
        $claim = DB::transaction(function () use ($actor, $purchaseId, $receipt) {
            app(NcbaPaymentService::class)->lockReceipt($receipt);
            $payment = NcbaPayment::where('transaction_id', $receipt)->lockForUpdate()->first();
            $purchase = TokenPurchase::where('user_id', $actor->id)->lockForUpdate()->findOrFail($purchaseId);
            $owner = $purchase->user;
            abort_unless($owner && $owner->is_active, 403);
            $latest = $purchase->receiptClaims()->orderByDesc('sequence')->lockForUpdate()->first();
            $phone = self::normalizePhone($owner->phone);
            if ($latest && $latest->receipt_number === $receipt
                && $latest->owner_phone_snapshot === $phone
                && $latest->phone_verified_at_snapshot?->toIso8601String() === $owner->phone_verified_at?->toIso8601String()) {
                $latest->increment('submission_count');
                $latest->update(['last_submitted_at' => now()]);
                app(NcbaPaymentAudit::class)->record('CLAIM_RESUBMITTED', $payment, $latest, $purchase, $actor);

                return $latest;
            }
            if (($latest?->sequence ?? 0) >= 20) {
                throw ValidationException::withMessages(['receipt_number' => 'Too many receipt changes for this purchase. Please contact support.']);
            }
            if ($latest && in_array($latest->status, [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW], true)) {
                $this->transition($latest, NcbaReceiptClaim::SUPERSEDED, 'REPLACED_BY_OWNER', $actor);
            }
            $claim = NcbaReceiptClaim::create([
                'token_purchase_id' => $purchase->id, 'user_id' => $owner->id,
                'receipt_number' => $receipt, 'sequence' => ($latest?->sequence ?? 0) + 1,
                'ncba_payment_id' => $payment?->id, 'status' => NcbaReceiptClaim::WAITING_BANK,
                'owner_phone_snapshot' => $phone, 'phone_verified_at_snapshot' => $owner->phone_verified_at,
                'purchase_snapshot' => $this->snapshot($purchase),
                'submitted_at' => now(), 'last_submitted_at' => now(),
            ]);
            app(NcbaPaymentAudit::class)->record('CLAIM_SUBMITTED', $payment, $claim, $purchase, $actor,
                ['purchase' => $this->snapshot($purchase), 'phone_verified' => (bool) $owner->phone_verified_at]);
            if ($purchase->status !== TokenPurchase::PENDING
                && ! ($purchase->status === TokenPurchase::PAID && $purchase->payment_reference === $receipt)) {
                $this->transition($claim, NcbaReceiptClaim::REJECTED, 'PURCHASE_NOT_PENDING', $actor);
            }

            return $claim;
        }, 3);

        // Commit the claim before reconciliation. A callback racing this request uses the same
        // durable receipt mutex, so either side sees and reconciles the committed counterpart.
        $this->reconcile($receipt);

        return $claim->fresh();
    }

    public function reconcile(string $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            app(NcbaPaymentService::class)->lockReceipt($receipt);
            $payment = NcbaPayment::where('transaction_id', $receipt)->lockForUpdate()->first();
            $this->reconcileLocked($receipt, $payment);
        }, 3);
    }

    /** Caller holds the receipt mutex and, if present, bank payment row. */
    public function reconcileLocked(string $receipt, ?NcbaPayment $payment): void
    {
        if ($payment) {
            // Link closed/replaced attempts for history as well, without reopening them.
            NcbaReceiptClaim::where('receipt_number', $receipt)->whereNull('ncba_payment_id')->update(['ncba_payment_id' => $payment->id]);
        }
        $claims = NcbaReceiptClaim::where('receipt_number', $receipt)
            ->whereIn('status', [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW])
            ->orderBy('token_purchase_id')->orderBy('sequence')->get();
        $purchases = TokenPurchase::whereIn('id', $claims->pluck('token_purchase_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $owners = User::whereIn('id', $purchases->pluck('user_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $eligible = [];
        foreach ($claims as $claim) {
            $purchase = $purchases->get($claim->token_purchase_id);
            $purchase->setRelation('user', $owners->get($purchase->user_id));
            $claim->refresh();
            if (! in_array($claim->status, [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW], true)) {
                continue;
            }
            if ($payment) {
                $claim->update(['ncba_payment_id' => $payment->id]);
            }
            if ($purchase->status === TokenPurchase::PAID && $purchase->payment_reference === $receipt
                && $payment?->token_purchase_id === $purchase->id && $payment->status === NcbaPayment::APPLIED) {
                $this->transition($claim, NcbaReceiptClaim::APPROVED, 'ALREADY_CONFIRMED');

                continue;
            }
            if ($purchase->status !== TokenPurchase::PENDING) {
                $this->transition($claim, NcbaReceiptClaim::REJECTED, 'PURCHASE_NOT_PENDING');

                continue;
            }
            if ($payment && in_array($payment->status, [NcbaPayment::APPLIED, NcbaPayment::RESOLVED], true)) {
                $this->transition($claim, NcbaReceiptClaim::REJECTED,
                    $payment->status === NcbaPayment::RESOLVED ? 'PAYMENT_RESOLVED_WITHOUT_TOKENS' : 'RECEIPT_UNAVAILABLE');

                continue;
            }
            if (! $payment) {
                $stale = $claim->submitted_at->lte(now()->subHours((int) config('ncba.claim_wait_hours', 48)));
                $this->transition($claim, $stale ? NcbaReceiptClaim::REVIEW : NcbaReceiptClaim::WAITING_BANK,
                    $stale ? 'BANK_CONFIRMATION_DELAYED' : null);

                continue;
            }
            $reason = $this->reviewReason($claim, $purchase, $payment);
            if ($reason) {
                $this->transition($claim, NcbaReceiptClaim::REVIEW, $reason);

                continue;
            }
            $eligible[] = [$claim, $purchase];
        }
        if (count($eligible) > 1) {
            foreach ($eligible as [$claim]) {
                $this->transition($claim, NcbaReceiptClaim::REVIEW, 'MULTIPLE_ELIGIBLE_PURCHASES');
            }
        } elseif (count($eligible) === 1) {
            [$claim, $purchase] = $eligible[0];
            app(NcbaPaymentService::class)->creditClaimLocked($payment, $purchase, $claim);
        }
        if ($payment?->status === NcbaPayment::REVIEW) {
            $reasons = NcbaReceiptClaim::where('receipt_number', $receipt)->where('status', NcbaReceiptClaim::REVIEW)
                ->pluck('reason_code')->filter()->unique()->implode(', ');
            $reason = $reasons ? substr('Receipt claims need review: '.$reasons, 0, 255)
                : (NcbaReceiptClaim::where('receipt_number', $receipt)->exists()
                    ? 'Owner claims are closed or replaced. Finance reconciliation is required.'
                    : 'Awaiting an owner receipt claim or finance reconciliation.');
            if ($payment->review_reason !== $reason) {
                $payment->update(['review_reason' => $reason]);
                app(NcbaPaymentAudit::class)->record('BANK_PAYMENT_REVIEW', $payment, details: ['reason' => $reason]);
            }
        }
    }

    private function reviewReason(NcbaReceiptClaim $claim, TokenPurchase $purchase, NcbaPayment $payment): ?string
    {
        if ($payment->status !== NcbaPayment::REVIEW || $payment->conflict_count > 0) {
            return 'RECEIPT_UNAVAILABLE';
        }
        if (! app(NcbaNotificationVerifier::class)->ready() || ! config('ncba.auto_credit') || ! config('ncba.contract_verified')) {
            return 'AUTOMATIC_APPROVAL_DISABLED';
        }
        if ($payment->alias !== config('ncba.alias') || $payment->shortcode !== '880100') {
            return 'RECIPIENT_REQUIRES_REVIEW';
        }
        if ($purchase->currency !== $payment->currency || PaymentAmount::minor($purchase->total_amount) !== $payment->amount_minor) {
            return 'AMOUNT_OR_CURRENCY_MISMATCH';
        }
        if ($claim->purchase_snapshot !== $this->snapshot($purchase)) {
            return 'PURCHASE_CHANGED';
        }
        $owner = $purchase->user;
        $phone = self::normalizePhone($owner?->phone);
        if (! $owner || ! $owner->is_active || ! $owner->phone_verified_at || ! $claim->phone_verified_at_snapshot
            || ! $phone || $phone !== $claim->owner_phone_snapshot
            || ! $claim->phone_verified_at_snapshot->equalTo($owner->phone_verified_at)
            || $phone !== self::normalizePhone($payment->payer_mobile)) {
            return 'OWNERSHIP_REQUIRES_REVIEW';
        }
        $local = substr($phone, 3);
        $verifiedOwners = User::whereIn('phone', [$phone, '+'.$phone, '0'.$local, $local])
            ->whereNotNull('phone_verified_at')->where('is_active', true)->count();
        if ($verifiedOwners !== 1) {
            return 'AMBIGUOUS_PHONE_OWNERSHIP';
        }
        if ($payment->transacted_at->lt($purchase->created_at->copy()->subMinutes(5))
            || $payment->transacted_at->gt(now()->addMinutes(5))
            || $owner->phone_verified_at->gt($payment->transacted_at->copy()->addMinutes(5))) {
            return 'PAYMENT_TIME_REQUIRES_REVIEW';
        }
        if (NcbaReceiptClaim::where('token_purchase_id', $purchase->id)->where('receipt_number', $claim->receipt_number)
            ->where('reason_code', 'STAFF_REJECTED')->exists()) {
            return 'PREVIOUS_STAFF_REJECTION';
        }
        if (TokenPurchase::where('payment_reference', $payment->transaction_id)->where('id', '!=', $purchase->id)->exists()) {
            return 'RECEIPT_UNAVAILABLE';
        }

        return null;
    }

    public function transition(NcbaReceiptClaim $claim, string $status, ?string $reason, ?User $actor = null, array $details = []): void
    {
        if ($claim->status === $status && $claim->reason_code === $reason) {
            return;
        }
        $previous = $claim->status;
        $claim->update(['status' => $status, 'reason_code' => $reason,
            'decided_at' => in_array($status, [NcbaReceiptClaim::APPROVED, NcbaReceiptClaim::REJECTED, NcbaReceiptClaim::SUPERSEDED], true) ? now() : null]);
        $event = app(NcbaPaymentAudit::class)->record('CLAIM_'.$status, claim: $claim, actor: $actor,
            details: ['previous_status' => $previous, 'reason_code' => $reason] + $details);
        if ($status === NcbaReceiptClaim::REVIEW) {
            app(NcbaPaymentAudit::class)->notifyAdmins($event, 'Payment claim needs review',
                "Receipt {$claim->receipt_number}; purchase {$claim->token_purchase_id}. Reason: {$reason}.");
        }
        if ($reason === 'STAFF_REJECTED') {
            \App\Models\Notification::create([
                'user_id' => $claim->user_id, 'title' => 'Payment claim needs attention',
                'message' => "Your claim for receipt {$claim->receipt_number} was not approved after review. Contact support for help.",
                'type' => 'warning', 'is_read' => false,
                'data' => ['purchase_id' => $claim->token_purchase_id, 'claim_id' => $claim->id],
            ]);
        }
    }

    public function settleClaims(NcbaPayment $payment, TokenPurchase $purchase, ?User $actor = null): void
    {
        foreach (NcbaReceiptClaim::where('receipt_number', $payment->transaction_id)
            ->whereIn('status', [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW])->lockForUpdate()->get() as $claim) {
            $claim->update(['ncba_payment_id' => $payment->id]);
            $same = $claim->token_purchase_id === $purchase->id;
            $this->transition($claim, $same ? NcbaReceiptClaim::APPROVED : NcbaReceiptClaim::REJECTED,
                $same ? ($actor ? 'STAFF_APPROVED' : 'BANK_AND_OWNER_VERIFIED') : 'RECEIPT_UNAVAILABLE', $actor);
        }
        foreach ($purchase->receiptClaims()->whereIn('status', [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW])->lockForUpdate()->get() as $claim) {
            $this->transition($claim, NcbaReceiptClaim::SUPERSEDED, 'PURCHASE_PAID_WITH_OTHER_RECEIPT', $actor);
        }
    }

    public function reject(string $claimId, User $actor, string $notes): void
    {
        abort_unless(AdminAccess::allows('finance', $actor), 403);
        validator(['notes' => $notes], ['notes' => ['required', 'string', 'min:10', 'max:4000']])->validate();
        $receipt = NcbaReceiptClaim::findOrFail($claimId)->receipt_number;
        DB::transaction(function () use ($claimId, $receipt, $actor, $notes) {
            app(NcbaPaymentService::class)->lockReceipt($receipt);
            $claim = NcbaReceiptClaim::findOrFail($claimId);
            TokenPurchase::lockForUpdate()->findOrFail($claim->token_purchase_id);
            $claim->refresh();
            if (! in_array($claim->status, [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW], true)) {
                throw ValidationException::withMessages(['notes' => 'Only an open claim can be rejected.']);
            }
            $this->transition($claim, NcbaReceiptClaim::REJECTED, 'STAFF_REJECTED', $actor, ['notes' => $notes]);
        }, 3);
    }

    public function snapshot(TokenPurchase $purchase): array
    {
        return ['user_id' => $purchase->user_id, 'token_type_id' => $purchase->token_type_id,
            'quantity' => $purchase->quantity, 'unit_price' => $purchase->unit_price,
            'total_amount' => $purchase->total_amount, 'currency' => $purchase->currency];
    }
}
