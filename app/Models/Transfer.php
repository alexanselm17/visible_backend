<?php

namespace App\Models;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'product_id', 'quantity', 'shift_id', 'to_station', 'from_station', 'processed_by', 'approved_by','approval_status'
    ];

    public $incrementing = false;  
    protected $keyType = 'string';  
  
    protected static function booted()
    {
        static::creating(function ($transfer) {
            $transfer->id = (string) Str::uuid();  // Automatically generate UUID
        });
    }

    // Relationship to the station (to_station)
    public function toStation()
    {
        return $this->belongsTo(Stations::class, 'to_station');
    }

    // Relationship to the user (processed_by)
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Relationship to the product
    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id');
    }
}
