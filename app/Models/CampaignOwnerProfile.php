<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CampaignOwnerProfile extends Model
{
    protected $table = 'campaign_owner_profiles';

    protected $fillable = [
        'user_id',
        'business_name',
        'business_category',
        'mpesa_phone',
        'website',
        'logo_path',
        'account_status',
        'current_subscription_id',
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function currentSubscription()
    {
        return $this->belongsTo(UserSubscription::class, 'current_subscription_id');
    }
    public function logos()
    {
        return $this->hasMany(\App\Models\CampaignOwnerLogo::class, 'profile_id');
    }
}
