<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdvertImages extends Model
{
    protected $table="advert_images";
    protected $fillable=['image_path','category','name','selling_price','campaign_id'];

    public function screenshots()
{
    return $this->hasMany(Screenshots::class, 'advert_id');
}
public function invoices()
{
    return $this->hasMany(Invoice::class, 'advert_id');
}
protected $keyType = 'string';
public $incrementing = false;
public function campaign()
{
    return $this->belongsTo(Campaign::class, 'campaign_id');
}

protected static function boot()
{
    parent::boot();

    static::creating(function ($model) {
        if (empty($model->{$model->getKeyName()})) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        }
    });
}



}
