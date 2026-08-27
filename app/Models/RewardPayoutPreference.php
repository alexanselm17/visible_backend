<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardPayoutPreference extends Model
{
    use HasUuids;

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    protected $fillable = ['user_id', 'frequency', 'effective_from'];

    protected $casts = ['effective_from' => 'datetime'];
}
