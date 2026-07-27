<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'locked_balance'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($wallet) {
            if (empty($wallet->id)) {
                $wallet->id = (string) Str::uuid();
            }
        });
    }

    // Accessor for Total Balance (Available + Locked)
    public function getTotalBalanceAttribute()
    {
        return $this->balance + $this->locked_balance;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class)->orderBy('created_at', 'desc');
    }

    public function holds()
    {
        return $this->hasMany(CreditHold::class)->orderBy('created_at', 'desc');
    }
}
