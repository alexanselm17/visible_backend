<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NcbaPayment extends Model
{
    use HasUuids;

    public const REVIEW = 'REVIEW';

    public const APPLIED = 'APPLIED';

    public const RESOLVED = 'RESOLVED';

    protected $guarded = ['id'];

    protected $hidden = ['payer_mobile', 'payer_name', 'fingerprint', 'verified_payload'];

    protected $casts = [
        'amount_minor' => 'integer', 'delivery_count' => 'integer', 'conflict_count' => 'integer',
        'payer_mobile' => 'encrypted', 'payer_name' => 'encrypted',
        'verified_payload' => 'encrypted:array',
        'transacted_at' => 'datetime', 'last_received_at' => 'datetime', 'last_conflict_at' => 'datetime',
        'applied_at' => 'datetime', 'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $payment) {
            if ($payment->isDirty(['transaction_id', 'bank_reference', 'alias', 'shortcode', 'amount_minor',
                'currency', 'transacted_at', 'narrative', 'payment_code', 'payer_mobile', 'payer_name',
                'verified_payload', 'fingerprint'])) {
                throw new \LogicException('Original verified bank receipt fields are immutable. Record a reconciliation event instead.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Bank receipts cannot be deleted.'));
    }

    public function purchase()
    {
        return $this->belongsTo(TokenPurchase::class, 'token_purchase_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function receiptClaims()
    {
        return $this->hasMany(NcbaReceiptClaim::class);
    }

    public function events()
    {
        return $this->hasMany(NcbaPaymentEvent::class)->orderBy('sequence');
    }
}
