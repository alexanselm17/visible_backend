<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class SysMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_index',
        'meta_key',
        'meta_value',
        'meta_shortcode',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public $incrementing = false;  
    protected $keyType = 'string';  
  
    protected static function booted()
    {
        static::creating(function ($sysMeta) {
            $sysMeta->id = (string) Str::uuid();  // Automatically generate UUID
        });
    }

    /**
     * Get the bankings associated with this SysMeta.
     */
    public function bankings()
    {
        return $this->hasMany(Banking::class, 'deposit_method');
    }
    public function petrolStation()
    {
        return $this->belongsTo(PetrolStation::class, 'petrol_id');
    }
}
