<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TokenWallet extends Model
{
    protected $fillable = [
        'user_id',
        'token_type_id',
        'balance',
        'locked_balance',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'balance' => 'integer',
        'locked_balance' => 'integer',
    ];

    protected $appends = ['total_balance'];

    protected static function booted(): void
    {
        static::creating(function (TokenWallet $wallet) {
            if (empty($wallet->id)) {
                $wallet->id = (string) Str::uuid();
            }
        });
    }

    public function getTotalBalanceAttribute(): int
    {
        return (int) $this->balance + (int) $this->locked_balance;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tokenType()
    {
        return $this->belongsTo(TokenType::class);
    }

    public function transactions()
    {
        return $this->hasMany(TokenTransaction::class)->latest();
    }

    public function holds()
    {
        return $this->hasMany(TokenHold::class)->latest();
    }
}
