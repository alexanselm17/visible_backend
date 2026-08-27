<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardMetricTier extends Model
{
    use HasUuids;

    protected $fillable = [
        'reward_plan_metric_id',
        'minimum_value',
        'maximum_value',
        'multiplier_basis_points',
        'sort_order',
    ];

    protected $casts = [
        'minimum_value' => 'decimal:4',
        'maximum_value' => 'decimal:4',
        'multiplier_basis_points' => 'integer',
        'sort_order' => 'integer',
    ];
}
