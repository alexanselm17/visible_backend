<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TokenPurchase extends Model
{
    public const PENDING = 'PENDING';

    public const PAID = 'PAID';

    public const FAILED = 'FAILED';

    public const CANCELLED = 'CANCELLED';

    public const REFUNDED = 'REFUNDED';

    protected $fillable = [
        'user_id',
        'token_type_id',
        'quantity',
        'unit_price',
        'total_amount',
        'currency',
        'status',
        'payment_reference',
        'payment_code',
        'paid_at',
        'metadata',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (TokenPurchase $purchase) {
            if (empty($purchase->id)) {
                $purchase->id = (string) Str::uuid();
            }
            do {
                $code = 'VD'.strtoupper(bin2hex(random_bytes(5)));
            } while (static::where('payment_code', $code)->exists());
            $purchase->payment_code = $code;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tokenType()
    {
        return $this->belongsTo(TokenType::class);
    }

    public function receiptClaims()
    {
        return $this->hasMany(NcbaReceiptClaim::class, 'token_purchase_id');
    }
}
