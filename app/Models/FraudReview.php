<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraudReview extends Model
{
    protected $casts = ['fraud_payload' => 'array', 'reviewed_at' => 'datetime'];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function advert()
    {
        return $this->belongsTo(AdvertImages::class, 'advert_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
