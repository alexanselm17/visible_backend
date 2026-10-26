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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
