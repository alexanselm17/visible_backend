<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardPeriod extends Model
{
    use HasUuids;

    public const OPEN = 'open';

    public const LOCKED = 'locked';

    public const PAYMENT_PENDING = 'payment_pending';

    public const PAID = 'paid';

    protected $fillable = [
        'user_id',
        'reward_plan_id',
        'frequency',
        'starts_at',
        'ends_at',
        'maximum_amount_minor',
        'currency',
        'calculation_method',
        'status',
        'combined_multiplier_basis_points',
        'calculated_amount_minor',
        'calculation_snapshot',
        'calculated_at',
        'locked_at',
        'paid_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'maximum_amount_minor' => 'integer',
        'combined_multiplier_basis_points' => 'integer',
        'calculated_amount_minor' => 'integer',
        'calculation_snapshot' => 'array',
        'calculated_at' => 'datetime',
        'locked_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(RewardPlan::class, 'reward_plan_id');
    }

    public function metrics()
    {
        return $this->hasMany(RewardPeriodMetric::class)->with('metric');
    }

    public function payout()
    {
        return $this->hasOne(RewardPayout::class);
    }
}
