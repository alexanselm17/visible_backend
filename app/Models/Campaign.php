<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
       
    ];

  

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function adverts()
    {
        return $this->hasMany(AdvertImages::class, 'campaign_id');
    }

    // Helper method to get total screenshots across all adverts
    public function getTotalScreenshotsAttribute()
    {
        return $this->adverts->sum(function ($advert) {
            return $advert->screenshots->count();
        });
    }

    // Helper method to get total views across all screenshots
    public function getTotalViewsAttribute()
    {
        return $this->adverts->sum(function ($advert) {
            return $advert->screenshots->sum('views');
        });
    }

    // Helper method to calculate total rewards distributed
    public function getTotalRewardsDistributedAttribute()
    {
        return $this->adverts->sum(function ($advert) {
            return $advert->screenshots->count() * $advert->reward;
        });
    }

    // Helper method to calculate remaining budget
    public function getRemainingBudgetAttribute()
    {
        return $this->capital_invested - $this->total_rewards_distributed;
    }

    // Check if campaign is active
    public function getIsActiveAttribute()
    {
        return $this->valid_until >= now();
    }

    // Scope for active campaigns
    public function scopeActive($query)
    {
        return $query->where('valid_until', '>=', now());
    }

    // Scope for expired campaigns
    public function scopeExpired($query)
    {
        return $query->where('valid_until', '<', now());
    }
}
