<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardLedgerEntry extends Model
{
    use HasUuids;

    public const EARNING = 'earning';

    public const PAYMENT = 'payment';

    public const ADJUSTMENT = 'adjustment';

    public const REVERSAL = 'reversal';

    protected $fillable = [
        'user_id',
        'reward_period_id',
        'reward_payout_id',
        'type',
        'amount_minor',
        'currency',
        'idempotency_key',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'metadata' => 'array',
    ];
}
