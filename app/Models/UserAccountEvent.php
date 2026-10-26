<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserAccountEvent extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'performed_by', 'action', 'reason', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'performed_by')->withTrashed();
    }
}
