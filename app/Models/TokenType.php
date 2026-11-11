<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TokenType extends Model
{
    public const GOLD = 'GOLD';

    public const PLATINUM = 'PLATINUM';

    public const EMERALD = 'EMERALD';

    public const SILVER = 'SILVER';

    public const VIDEO = 'VIDEO';

    public const IMAGE = 'IMAGE';

    public const ADS_IMAGE = 'ADS_IMAGE';

    public const TEXT = 'TEXT';

    public static function codes(): array
    {
        return [self::GOLD, self::PLATINUM, self::EMERALD, self::SILVER];
    }

    public static function mediaOptions(): array
    {
        return [self::VIDEO => 'Video', self::IMAGE => 'Full image (standalone)', self::ADS_IMAGE => 'Ads image (attached to a member’s image)', self::TEXT => 'Text'];
    }

    public static function normalizeMediaType(string $type): string
    {
        return match (strtoupper(trim($type))) {
            'ADS' => self::ADS_IMAGE,
            'FULL_IMAGE' => self::IMAGE,
            default => strtoupper(trim($type)),
        };
    }

    public static function isImageMedia(?string $type): bool
    {
        return in_array(self::normalizeMediaType($type ?? ''), [self::IMAGE, self::ADS_IMAGE], true);
    }

    public static function codeForMedia(string $type): ?string
    {
        return match (self::normalizeMediaType($type)) {
            self::VIDEO => self::GOLD,
            self::IMAGE => self::PLATINUM,
            self::ADS_IMAGE => self::EMERALD,
            self::TEXT => self::SILVER,
            default => null,
        };
    }

    protected $fillable = [
        'code',
        'name',
        'media_type',
        'unit_price',
        'currency',
        'seconds_per_token',
        'people_per_token',
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
        'people_per_token' => 'integer',
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
