<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PhoneVerificationOtp extends Model
{
    use HasUuids;

    public const SIGNUP = 'signup';

    protected $fillable = [
        'user_id',
        'phone',
        'purpose',
        'code_hash',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
        'sent_at',
        'delivery_reference',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
