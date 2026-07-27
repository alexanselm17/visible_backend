<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreditPlan extends Model
{
    protected $fillable = [
        'name',
        'price',
        'base_credits',
        'bonus_credits',
        'promoters_reach',
        'is_active'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($plan) {
            if (empty($plan->id)) {
                $plan->id = (string) Str::uuid();
            }
        });
    }

    public function getTotalCreditsAttribute(): float
    {
        return $this->base_credits + $this->bonus_credits;
    }
}
