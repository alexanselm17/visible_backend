<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Banking extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'amount',
        'name',
        'processed_by',
        'approval_status',
        'approved_by',
        'phone',
        'deposit_method',
        'transaction_id',

    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($banking) {
            $banking->id = (string) Str::uuid();
        });
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function depositMethod()
    {
        return $this->belongsTo(SysMeta::class, 'deposit_method');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'banking');
    }
}
