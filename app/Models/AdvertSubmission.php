<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdvertSubmission extends Model
{
    protected $table = 'advert_submissions';

    protected $fillable = [
        'campaign_id',
        'submitted_by',
        'capital_invested',
        'name',
        'description',
        'target_audience',
        'final_image_path',
        'final_video_path',
        'status',
        'designed_by',
        'designed_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'target_audience' => 'array',
        'capital_invested' => 'decimal:2',
    ];

    protected $appends = [
        'original_images',
        'original_videos',
        'final_image_url',
        'final_video_url',
        'final_thumbnail_url',

    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function media()
    {
        return $this->hasMany(AdvertSubmissionMedia::class, 'submission_id')
            ->orderBy('sort_order');
    }

    public function getOriginalImagesAttribute()
    {
        return $this->relationLoaded('media')
            ? $this->media->where('type', 'IMAGE')->values()
            : [];
    }

    public function getOriginalVideosAttribute()
    {
        return $this->relationLoaded('media')
            ? $this->media->where('type', 'VIDEO')->values()
            : [];
    }

    public function getFinalImageUrlAttribute()
    {
        return $this->final_image_path ? asset('storage/' . $this->final_image_path) : null;
    }

    public function getFinalVideoUrlAttribute()
    {
        return $this->final_video_path ? asset('storage/' . $this->final_video_path) : null;
    }

    public function getFinalThumbnailUrlAttribute()
    {
        return $this->final_thumbnail_path ? asset('storage/' . $this->final_thumbnail_path) : null;
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function designer()
    {
        return $this->belongsTo(User::class, 'designed_by');
    }
}
