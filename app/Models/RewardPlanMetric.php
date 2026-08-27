<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardPlanMetric extends Model
{
    use HasUuids;

    protected $fillable = [
        'reward_plan_id',
        'reward_metric_id',
        'weight_basis_points',
        'minimum_basis_points',
        'maximum_basis_points',
        'settings',
    ];

    protected $casts = [
        'weight_basis_points' => 'integer',
        'minimum_basis_points' => 'integer',
        'maximum_basis_points' => 'integer',
        'settings' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(RewardPlan::class, 'reward_plan_id');
    }

    public function metric()
    {
        return $this->belongsTo(RewardMetric::class, 'reward_metric_id');
    }

    public function tiers()
    {
        return $this->hasMany(RewardMetricTier::class)->orderBy('sort_order');
    }
}
