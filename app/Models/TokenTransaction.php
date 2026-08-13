<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TokenTransaction extends Model
{
    public const PURCHASE = 'PURCHASE';
    public const HOLD = 'HOLD';
    public const SPEND = 'SPEND';
    public const RELEASE = 'RELEASE';
    public const REFUND = 'REFUND';
    public const ADJUSTMENT = 'ADJUSTMENT';

    protected $fillable = [
        'token_wallet_id',
        'type',
        'amount',
        'description',
        'reference_id',
        'reference_type',
        'metadata',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (TokenTransaction $transaction) {
            if (empty($transaction->id)) {
                $transaction->id = (string) Str::uuid();
            }
        });
    }

    public function wallet()
    {
        return $this->belongsTo(TokenWallet::class, 'token_wallet_id');
    }
}
