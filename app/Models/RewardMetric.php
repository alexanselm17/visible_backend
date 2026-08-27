<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RewardMetric extends Model
{
    use HasUuids;

    public const VIEWS = 'views';

    public const CONSISTENCY = 'consistency';

    public const CONVERSION = 'conversion';

    protected $fillable = [
        'code',
        'name',
        'evaluator_key',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
