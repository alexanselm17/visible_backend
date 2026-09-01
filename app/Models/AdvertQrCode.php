<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdvertQrCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'advert_id',
        'identifier_snapshot',
        'token_hash',
        'generated_at',
        'last_verified_at',
    ];

    protected $hidden = [
        'token_hash',
        'identifier_snapshot',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function advert()
    {
        return $this->belongsTo(AdvertImages::class, 'advert_id');
    }
}
