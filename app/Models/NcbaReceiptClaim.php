<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NcbaReceiptClaim extends Model
{
    use HasUuids;

    public const WAITING_BANK = 'WAITING_BANK';

    public const REVIEW = 'REVIEW';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const SUPERSEDED = 'SUPERSEDED';

    protected $guarded = ['id'];

    protected $hidden = ['owner_phone_snapshot', 'phone_verified_at_snapshot', 'purchase_snapshot'];

    protected $casts = [
        'owner_phone_snapshot' => 'encrypted', 'purchase_snapshot' => 'encrypted:array',
        'phone_verified_at_snapshot' => 'datetime', 'submitted_at' => 'datetime',
        'last_submitted_at' => 'datetime', 'decided_at' => 'datetime',
        'sequence' => 'integer', 'submission_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $claim) {
            if ($claim->isDirty(['token_purchase_id', 'user_id', 'receipt_number', 'sequence',
                'owner_phone_snapshot', 'phone_verified_at_snapshot', 'purchase_snapshot', 'submitted_at'])) {
                throw new \LogicException('Receipt identity and submission snapshots are immutable. Submit a new numbered attempt.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Financial receipt claims cannot be deleted.'));
    }

    public function purchase()
    {
        return $this->belongsTo(TokenPurchase::class, 'token_purchase_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(NcbaPayment::class, 'ncba_payment_id');
    }

    public function events()
    {
        return $this->hasMany(NcbaPaymentEvent::class, 'receipt_claim_id')->orderBy('sequence');
    }

    public function ownerData(): array
    {
        return [
            'id' => $this->id, 'receipt_number' => $this->receipt_number, 'sequence' => $this->sequence,
            'status' => $this->status, 'reason_code' => $this->status === self::REVIEW ? 'MANUAL_REVIEW_REQUIRED' : $this->reason_code,
            'message' => match ($this->status) {
                self::APPROVED => 'Payment confirmed and tokens credited.',
                self::WAITING_BANK => 'Receipt saved. Waiting for confirmation from NCBA.',
                self::SUPERSEDED => 'Replaced by a later receipt submission. Retained in your history.',
                self::REJECTED => 'This receipt claim was not approved. Contact support for assistance.',
                default => 'Your payment needs an administrator to review it. No additional tokens have been credited.',
            },
            'submission_count' => $this->submission_count, 'submitted_at' => $this->submitted_at,
            'last_submitted_at' => $this->last_submitted_at, 'decided_at' => $this->decided_at,
        ];
    }
}
