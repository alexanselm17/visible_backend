<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardPayout extends Model
{
    use HasUuids;

    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    protected $fillable = [
        'reward_period_id',
        'user_id',
        'amount_minor',
        'currency',
        'status',
        'provider',
        'payment_reference',
        'idempotency_key',
        'failure_reason',
        'processed_by',
        'processing_at',
        'paid_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'processing_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function period()
    {
        return $this->belongsTo(RewardPeriod::class, 'reward_period_id');
    }
}
