<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreditHold extends Model
{
    protected $fillable = [
        'wallet_id',
        'advert_submission_id',
        'amount_locked',
        'amount_spent',
        'amount_released',
        'status'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($hold) {
            if (empty($hold->id)) {
                $hold->id = (string) Str::uuid();
            }
        });
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
