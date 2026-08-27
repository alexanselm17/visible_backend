<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardPeriodMetric extends Model
{
    use HasUuids;

    protected $fillable = [
        'reward_period_id',
        'reward_metric_id',
        'measured_value',
        'multiplier_basis_points',
        'evidence',
    ];

    protected $casts = [
        'measured_value' => 'decimal:4',
        'multiplier_basis_points' => 'integer',
        'evidence' => 'array',
    ];

    public function metric()
    {
        return $this->belongsTo(RewardMetric::class, 'reward_metric_id');
    }
}
