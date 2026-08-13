<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TokenHold extends Model
{
    public const ACTIVE = 'ACTIVE';
    public const SETTLED = 'SETTLED';
    public const PARTIALLY_SETTLED = 'PARTIALLY_SETTLED';
    public const CANCELLED = 'CANCELLED';

    protected $fillable = [
        'token_wallet_id',
        'advert_submission_id',
        'amount_locked',
        'amount_spent',
        'amount_released',
        'status',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'amount_locked' => 'integer',
        'amount_spent' => 'integer',
        'amount_released' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (TokenHold $hold) {
            if (empty($hold->id)) {
                $hold->id = (string) Str::uuid();
            }
        });
    }

    public function wallet()
    {
        return $this->belongsTo(TokenWallet::class, 'token_wallet_id');
    }

    public function submission()
    {
        return $this->belongsTo(AdvertSubmission::class, 'advert_submission_id');
    }
}
