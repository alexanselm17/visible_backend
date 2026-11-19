<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class NcbaPaymentEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['evidence'];

    protected $casts = ['details' => 'array', 'evidence' => 'encrypted:array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($event) => $event->created_at ??= now());
        static::updating(fn () => throw new LogicException('Payment audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment audit events cannot be deleted.'));
    }

    public function purchase()
    {
        return $this->belongsTo(TokenPurchase::class, 'token_purchase_id');
    }

    public function payment()
    {
        return $this->belongsTo(NcbaPayment::class, 'ncba_payment_id');
    }

    public function claim()
    {
        return $this->belongsTo(NcbaReceiptClaim::class, 'receipt_claim_id');
    }
}
