<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdvertImages extends Model
{
    protected $table = "advert_images";

    protected $fillable = [
        'image_path',
        'category',
        'name',
        'selling_price',
        'campaign_id',
        'reward',
        'description',
        'badge'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'badge' => 'array',
        'selling_price' => 'decimal:2',
        'reward' => 'decimal:2',
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

    public function screenshots()
    {
        return $this->hasMany(Screenshots::class, 'advert_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'advert_id');
    }

    // Helper method to get total views for this advert
    public function getTotalViewsAttribute()
    {
        return $this->screenshots->sum('views');
    }

    // Helper method to get total rewards distributed for this advert
    public function getTotalRewardsDistributedAttribute()
    {
        return $this->screenshots->count() * $this->reward;
    }

    // Helper method to get unique users who submitted screenshots
    public function getUniqueUsersAttribute()
    {
        return $this->screenshots->pluck('processed_by')->unique()->count();
    }
}
