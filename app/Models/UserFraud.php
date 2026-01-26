<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFraud extends Model
{
    protected $fillable = [
        'user_id',
        'reason',
        'details',
        'reported_by',
        'flagged_at',
    ];

    protected $casts = [
        'flagged_at' => 'datetime',
    ];
}
