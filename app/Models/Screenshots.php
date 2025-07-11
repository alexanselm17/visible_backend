<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Screenshots extends Model
{
    protected $fillable = ['screenshot', 'advert_id', 'processed_by', 'views'];


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
        return $this->belongsTo(User::class, 'processed_by');
    }
    public function advert()
    {
        return $this->belongsTo(AdvertImages::class, 'advert_id');
    }
}
