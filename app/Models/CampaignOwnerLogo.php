<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CampaignOwnerLogo extends Model
{
    protected $table = 'campaign_owner_logos';

    protected $fillable = [
        'profile_id',
        'logo_path',
        'is_primary',
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

    public function profile()
    {
        return $this->belongsTo(CampaignOwnerProfile::class, 'profile_id');
    }
}

