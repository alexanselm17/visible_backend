<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TokenType extends Model
{
    public const GOLD = 'GOLD';
    public const PLATINUM = 'PLATINUM';
    public const SILVER = 'SILVER';

    public const VIDEO = 'VIDEO';
    public const IMAGE = 'IMAGE';
    public const TEXT = 'TEXT';

    protected $fillable = [
        'code',
        'name',
        'media_type',
        'unit_price',
        'currency',
        'seconds_per_token',
        'max_video_duration_seconds',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'unit_price' => 'decimal:2',
        'seconds_per_token' => 'integer',
        'max_video_duration_seconds' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (TokenType $tokenType) {
            if (empty($tokenType->id)) {
                $tokenType->id = (string) Str::uuid();
            }
        });
    }

    public function wallets()
    {
        return $this->hasMany(TokenWallet::class);
    }

    public function purchases()
    {
        return $this->hasMany(TokenPurchase::class);
    }
}
