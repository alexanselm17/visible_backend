<?php

namespace App\Services;

use App\Models\NcbaPayment;
use App\Models\NcbaReceiptClaim;
use App\Models\Notification;
use App\Models\TokenPurchase;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\PaymentAmount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NcbaPaymentService
{
    public function instructions(TokenPurchase $purchase): ?array
    {
        if (! app(NcbaNotificationVerifier::class)->ready() || $purchase->status !== TokenPurchase::PENDING
            || $purchase->currency !== 'KES') {
            return null;
        }
        $account = config('ncba.alias');
        if (strlen($account) > (int) config('ncba.account_max_length', 20)) {
            return null;
        }

        return [
            'provider' => 'NCBA', 'method' => 'PAYBILL', 'paybill' => '880100',
            'account_number' => $account, 'alias' => config('ncba.alias'),
            'purchase_reference' => $purchase->payment_code, 'amount' => $purchase->total_amount,
            'currency' => $purchase->currency,
            'confirmation_method' => 'RECEIPT_CLAIM',
            'receipt_submission_url' => url('/api/v1/tokens/purchases/'.$purchase->id.'/receipt'),
            'message' => 'Pay to this account number only, then submit the M-Pesa receipt number against this purchase. Do not append the purchase reference. Automatic approval requires bank confirmation, the exact amount and your verified payer phone; otherwise an administrator reviews it.',
        ];
    }

    /** Accept only the verifier's sanitized output; never persist a raw request. */
    public function receive(array $data): bool
    {
        $amount = PaymentAmount::minor($data['TransAmount']);
        $transaction = strtoupper($data['TransID']);
        $bankReference = filled($data['FTRef'] ?? null) ? strtoupper($data['FTRef']) : null;
        $narrative = trim($data['Narrative'] ?? '');
        $code = strtoupper($narrative);
        $code = preg_match('/^VD[A-F0-9]{10}$/D', $code) ? $code : null;
        $fingerprint = hash('sha256', json_encode([
            $data['TransType'], $transaction, $bankReference, $data['TransTime'], $amount,
            $data['BusinessShortCode'], $data['BillRefNumber'], $narrative, $data['Mobile'], $data['name'],
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $amount, $transaction, $bankReference, $narrative, $code, $fingerprint) {
            $this->lockReceipt($transaction);
            $incoming = new NcbaPayment([
                'transaction_id' => $transaction, 'bank_reference' => $bankReference,
                'shortcode' => $data['BusinessShortCode'], 'alias' => $data['BillRefNumber'],
                'amount_minor' => $amount, 'currency' => 'KES',
                'transacted_at' => Carbon::createFromFormat('!YmdHis', $data['TransTime'], 'Africa/Nairobi')->timezone(config('app.timezone')),
                'narrative' => $narrative ?: null, 'payment_code' => $code,
                'payer_mobile' => $data['Mobile'], 'payer_name' => $data['name'],
                'verified_payload' => $data,
                'fingerprint' => $fingerprint, 'status' => NcbaPayment::REVIEW,
                'delivery_count' => 1, 'conflict_count' => 0, 'last_received_at' => now(),
            ]);
            $incoming->id = (string) Str::uuid();
            $incoming->created_at = $incoming->updated_at = now();
            // Unique indexes arbitrate concurrent deliveries, not a check-then-insert race.
            $inserted = DB::table('ncba_payments')->insertOrIgnore($incoming->getAttributes());
            $payment = NcbaPayment::where(function ($query) use ($transaction, $bankReference) {
                $query->where('transaction_id', $transaction);
                if ($bankReference !== null) {
                    $query->orWhere('bank_reference', $bankReference);
                }
            })->orderBy('id')->lockForUpdate()->firstOrFail();

            if (! hash_equals($payment->fingerprint, $fingerprint)) {
                $payment->increment('conflict_count');
                $payment->update(['last_conflict_at' => now(), 'last_received_at' => now()]);
                $event = app(NcbaPaymentAudit::class)->record('CALLBACK_CONFLICT', $payment,
                    details: ['incoming_fingerprint' => $fingerprint, 'conflict_count' => $payment->conflict_count,
                        'incoming_transaction_id' => $transaction, 'incoming_bank_reference' => $bankReference], source: 'BANK', evidence: $data);
                if ($payment->conflict_count === 1) {
                    app(NcbaPaymentAudit::class)->notifyAdmins($event, 'NCBA receipt conflict',
                        "Receipt {$payment->transaction_id} has conflicting bank deliveries. Investigate before allocating funds; do not automatically reverse any existing credit.");
                }

                return false;
            }
            if (! $inserted) {
                $payment->increment('delivery_count');
                $payment->update(['last_received_at' => now()]);
                app(NcbaPaymentAudit::class)->record('CALLBACK_REDELIVERED', $payment,
                    details: ['delivery_count' => $payment->delivery_count], source: 'BANK');
                app(NcbaReceiptClaimService::class)->reconcileLocked($transaction, $payment);

                return true;
            }

            $event = app(NcbaPaymentAudit::class)->record('BANK_PAYMENT_RECEIVED', $payment,
                details: ['amount_minor' => $amount, 'currency' => 'KES', 'bank_reference' => $bankReference,
                    'transacted_at' => $payment->transacted_at->toIso8601String(), 'alias' => $payment->alias], source: 'BANK');
            $payment->update(['review_reason' => 'Awaiting an owner receipt claim or finance reconciliation.']);
            app(NcbaReceiptClaimService::class)->reconcileLocked($transaction, $payment);
            if ($payment->fresh()->status === NcbaPayment::REVIEW) {
                app(NcbaPaymentAudit::class)->notifyAdmins($event, 'NCBA payment awaiting allocation',
                    "Receipt {$transaction}: KES ".PaymentAmount::decimal($amount).'. Bank confirmation received; no automatic token credit has been made.');
            }

            return true;
        }, 3);
    }

    public function apply(string $paymentId, string $purchaseId, User $actor, string $notes): NcbaPayment
    {
        abort_unless(AdminAccess::allows('finance', $actor), 403);
        validator(['notes' => $notes], ['notes' => ['required', 'string', 'min:10', 'max:4000']])->validate();

        return DB::transaction(function () use ($paymentId, $purchaseId, $actor, $notes) {
            $this->lockReceipt(NcbaPayment::findOrFail($paymentId)->transaction_id);
            $payment = NcbaPayment::lockForUpdate()->findOrFail($paymentId);
            $purchase = TokenPurchase::lockForUpdate()->findOrFail($purchaseId);
            if ($payment->status === NcbaPayment::APPLIED && $payment->token_purchase_id === $purchase->id) {
                return $payment;
            }
            $reason = $this->allocationReviewReason($payment, $purchase);
            if ($reason !== null) {
                throw ValidationException::withMessages(['token_purchase_id' => $reason]);
            }
            $this->applyLocked($payment, $purchase, $actor, $notes);
            $payment->update(['reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes]);

            return $payment->fresh();
        }, 3);
    }

    public function resolve(string $paymentId, User $actor, string $notes): void
    {
        abort_unless(AdminAccess::allows('finance', $actor), 403);
        validator(['notes' => $notes], ['notes' => ['required', 'string', 'min:10', 'max:4000']])->validate();
        DB::transaction(function () use ($paymentId, $actor, $notes) {
            $this->lockReceipt(NcbaPayment::findOrFail($paymentId)->transaction_id);
            $payment = NcbaPayment::lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== NcbaPayment::REVIEW) {
                throw ValidationException::withMessages(['notes' => 'Only an unallocated payment can be resolved without crediting tokens.']);
            }
            $payment->update([
                'status' => NcbaPayment::RESOLVED, 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'review_notes' => $notes, 'review_reason' => null,
            ]);
            $event = app(NcbaPaymentAudit::class)->record('BANK_PAYMENT_RESOLVED', $payment, actor: $actor, details: ['notes' => $notes]);
            foreach (NcbaReceiptClaim::where('receipt_number', $payment->transaction_id)
                ->whereIn('status', [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW])->get() as $claim) {
                app(NcbaReceiptClaimService::class)->transition($claim, NcbaReceiptClaim::REJECTED, 'PAYMENT_RESOLVED_WITHOUT_TOKENS', $actor);
            }
            app(NcbaPaymentAudit::class)->notifyAdmins($event, 'NCBA reconciliation recorded',
                "Receipt {$payment->transaction_id} was resolved outside token crediting. No money was transferred by this action.");
        });
    }

    public function lockReceipt(string $receipt): void
    {
        DB::table('ncba_receipt_keys')->insertOrIgnore(['receipt_number' => $receipt, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ncba_receipt_keys')->where('receipt_number', $receipt)->lockForUpdate()->first();
    }

    /** Internal reconciliation entry point; claim service has verified payer ownership and gates. */
    public function creditClaimLocked(NcbaPayment $payment, TokenPurchase $purchase, NcbaReceiptClaim $claim): void
    {
        $reason = $this->allocationReviewReason($payment, $purchase);
        if ($reason !== null) {
            app(NcbaReceiptClaimService::class)->transition($claim, NcbaReceiptClaim::REVIEW, 'ALLOCATION_REQUIRES_REVIEW');

            return;
        }
        $this->applyLocked($payment, $purchase);
    }

    private function allocationReviewReason(NcbaPayment $payment, TokenPurchase $purchase): ?string
    {
        if ($payment->status !== NcbaPayment::REVIEW || $payment->conflict_count > 0) {
            return 'This receipt is already allocated, resolved or has conflicting deliveries. Investigate with the bank.';
        }
        if (! $purchase->user || ! $purchase->user->is_active) {
            return 'The purchase owner is missing or inactive.';
        }
        if ($purchase->status !== TokenPurchase::PENDING
            && ! ($purchase->status === TokenPurchase::PAID && $purchase->payment_reference === $payment->transaction_id)) {
            return 'The purchase is no longer pending or was paid using another receipt.';
        }
        if ($purchase->currency !== $payment->currency || PaymentAmount::minor($purchase->total_amount) !== $payment->amount_minor) {
            return 'Payment amount or currency does not match the saved purchase total. Do not force-credit; investigate or arrange a refund.';
        }
        $unitPrice = PaymentAmount::minor($purchase->unit_price);
        if ($purchase->quantity < 1 || $unitPrice < 1 || $purchase->quantity > intdiv(PHP_INT_MAX, $unitPrice)
            || $unitPrice * $purchase->quantity !== $payment->amount_minor) {
            return 'The saved price, quantity and total do not agree. Investigate the purchase record.';
        }
        if (TokenPurchase::where('payment_reference', $payment->transaction_id)->where('id', '!=', $purchase->id)->exists()) {
            return 'This receipt was already used for another purchase.';
        }

        return null;
    }

    private function applyLocked(NcbaPayment $payment, TokenPurchase $purchase, ?User $actor = null, ?string $notes = null): void
    {
        $wasPaid = $purchase->status === TokenPurchase::PAID;
        $payment->update(['token_purchase_id' => $purchase->id]);
        app(TokenService::class)->confirmPurchase($purchase->id, $payment->transaction_id);
        $payment->update(['status' => NcbaPayment::APPLIED, 'review_reason' => null, 'applied_at' => now()]);
        app(NcbaReceiptClaimService::class)->settleClaims($payment, $purchase, $actor);
        $event = app(NcbaPaymentAudit::class)->record('TOKENS_CREDITED', $payment, purchase: $purchase, actor: $actor,
            details: ['method' => $actor ? 'STAFF_RECONCILIATION' : 'VERIFIED_RECEIPT_CLAIM',
                'purchase' => app(NcbaReceiptClaimService::class)->snapshot($purchase), 'notes' => $notes, 'already_paid' => $wasPaid]);
        app(NcbaPaymentAudit::class)->notifyAdmins($event, 'NCBA purchase approved',
            "Receipt {$payment->transaction_id}: KES {$purchase->total_amount}; {$purchase->quantity} {$purchase->tokenType->code} tokens for {$purchase->user->fullname}. Purchase {$purchase->id}.".($wasPaid ? ' Previously credited; no additional tokens were added.' : ' Wallet credited successfully.'));
        if (! $wasPaid) {
            Notification::create([
                'user_id' => $purchase->user_id, 'title' => 'Payment received', 'type' => 'success',
                'message' => "Your payment of KES {$purchase->total_amount} was received. {$purchase->quantity} {$purchase->tokenType->code} tokens have been credited.",
                'is_read' => false,
                'data' => ['purchase_id' => $purchase->id, 'payment_reference' => $payment->transaction_id, 'provider' => 'NCBA'],
            ]);
        }
    }
}
