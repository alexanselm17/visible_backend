<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PrivacyRequest extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id', 'reference'];

    protected $casts = ['completed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            $request->id = (string) Str::uuid();
            $request->reference = 'VDM-'.strtoupper(Str::random(12));
        });
    }
}
