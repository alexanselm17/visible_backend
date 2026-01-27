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
        'original_image_path',
        'original_video_path',
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
