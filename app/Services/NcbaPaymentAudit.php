<?php

namespace App\Services;

use App\Models\NcbaPayment;
use App\Models\NcbaPaymentEvent;
use App\Models\NcbaReceiptClaim;
use App\Models\Notification;
use App\Models\TokenPurchase;
use App\Models\User;

class NcbaPaymentAudit
{
    public function record(string $type, ?NcbaPayment $payment = null, ?NcbaReceiptClaim $claim = null,
        ?TokenPurchase $purchase = null, ?User $actor = null, array $details = [], string $source = 'SYSTEM', ?array $evidence = null): NcbaPaymentEvent
    {
        return NcbaPaymentEvent::create([
            'event_type' => $type, 'ncba_payment_id' => $payment?->id ?? $claim?->ncba_payment_id,
            'receipt_claim_id' => $claim?->id, 'token_purchase_id' => $purchase?->id ?? $claim?->token_purchase_id,
            'receipt_number' => $payment?->transaction_id ?? $claim?->receipt_number,
            'actor_id' => $actor?->id, 'actor_type' => $actor ? 'USER' : $source, 'details' => $details, 'evidence' => $evidence,
        ]);
    }

    public function notifyAdmins(NcbaPaymentEvent $event, string $title, string $message): void
    {
        // Persist inside the same transaction as the decision. Delivery never depends on a queue.
        foreach (User::where('is_active', true)->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'dev']))->cursor() as $admin) {
            Notification::create([
                'user_id' => $admin->id, 'title' => $title, 'message' => $message, 'type' => 'info', 'is_read' => false,
                'data' => ['category' => 'ncba_payment', 'event_id' => $event->id,
                    'payment_id' => $event->ncba_payment_id, 'claim_id' => $event->receipt_claim_id,
                    'purchase_id' => $event->token_purchase_id, 'receipt_number' => $event->receipt_number],
            ]);
        }
    }
}
