<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdvertImages extends Model
{
    protected $table = 'advert_images';

    protected $fillable = [
        'image_path',
        'video_path',
        'category',
        'name',
        'selling_price',
        'campaign_id',
        'description',
        'badge',
        'capital_invested',
        'valid_until',
        'capacity',
        'target_audience',
        'type',
        'submission_id',
        'repost_of_id',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'badge' => 'array',
        'selling_price' => 'decimal:2',
        'target_audience' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });

        static::saving(function (self $advert) {
            if ($advert->exists && $advert->isDirty('type') && $advert->submission_id) {
                $fundedType = $advert->submission()->value('type');
                if ($fundedType && TokenType::normalizeMediaType($fundedType) !== $advert->mediaType()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'type' => 'A funded advert must keep the format paid for by its submission. Submit a new advert to change formats.',
                    ]);
                }
            }
        });
    }

    public function mediaType(): string
    {
        return TokenType::normalizeMediaType($this->type ?: ($this->video_path ? TokenType::VIDEO : TokenType::IMAGE));
    }

    public function requiresPersonalImage(): bool
    {
        return $this->mediaType() === TokenType::ADS_IMAGE;
    }

    public function deliveryDetails(): array
    {
        $type = $this->mediaType();

        return [
            'media_type' => $type,
            'posting_format' => $type === TokenType::IMAGE ? 'FULL_IMAGE' : $type,
            'token_type' => TokenType::codeForMedia($type),
            'requires_personal_image' => $this->requiresPersonalImage(),
            'download_url' => $this->requiresPersonalImage() ? null : route('download.advert.personalized', ['advertId' => $this->id]),
            'stamp_url' => $this->requiresPersonalImage() ? url('/api/v1/image/stamp') : null,
        ];
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function screenshots()
    {
        return $this->hasMany(Screenshots::class, 'advert_id');
    }

    // Helper method to get total views for this advert
    public function getTotalViewsAttribute()
    {
        return $this->screenshots->sum('views');
    }

    // Helper method to get unique users who submitted screenshots
    public function getUniqueUsersAttribute()
    {
        return $this->screenshots->pluck('processed_by')->unique()->count();
    }

    public function submission()
    {
        return $this->belongsTo(AdvertSubmission::class, 'submission_id');
    }

    public function repostOf()
    {
        return $this->belongsTo(AdvertImages::class, 'repost_of_id');
    }

    public function reposts()
    {
        return $this->hasMany(AdvertImages::class, 'repost_of_id');
    }
}
