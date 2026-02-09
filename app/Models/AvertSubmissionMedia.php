<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdvertSubmissionMedia extends Model
{
    protected $table = 'advert_submission_media';

    protected $fillable = [
        'submission_id',
        'type',
        'path',
        'original_name',
        'sort_order',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $appends = ['url'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (empty($m->{$m->getKeyName()})) {
                $m->{$m->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function getUrlAttribute()
    {
        return $this->path ? asset('storage/' . $this->path) : null;
    }

    public function submission()
    {
        return $this->belongsTo(AdvertSubmission::class, 'submission_id');
    }
}
