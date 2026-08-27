<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardPlan extends Model
{
    use HasUuids;

    public const MULTIPLY = 'multiply';

    public const WEIGHTED_AVERAGE = 'weighted_average';

    protected $fillable = [
        'name',
        'version',
        'calculation_method',
        'monthly_maximum_minor',
        'currency',
        'is_active',
        'effective_from',
        'effective_until',
        'settings',
    ];

    protected $casts = [
        'version' => 'integer',
        'monthly_maximum_minor' => 'integer',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'settings' => 'array',
    ];

    public function planMetrics()
    {
        return $this->hasMany(RewardPlanMetric::class)->with(['metric', 'tiers']);
    }
}
