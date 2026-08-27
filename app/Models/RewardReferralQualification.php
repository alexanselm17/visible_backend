<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardReferralQualification extends Model
{
    use HasUuids;

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'qualifying_advert_id',
        'qualified_at',
        'evidence',
    ];

    protected $casts = [
        'qualified_at' => 'datetime',
        'evidence' => 'array',
    ];
}
