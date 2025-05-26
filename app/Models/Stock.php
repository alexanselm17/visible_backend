<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class Stock extends Model
{

    protected $table = 'stocks';
    protected $fillable = [
        'drum_id',
        'station_id',
        'stock',
        'product_id',

    ];
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($stock) {
            $stock->id = (string) Str::uuid();  // Automatically generate UUID
        });
    }

     protected $casts = [
        'stock' => 'decimal:2',
    ];



    // Adjusted product relationship to check for product_id from drum if stock product_id is null
    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id')
            ->withDefault(function () {
                return $this->drum ? $this->drum->product : null;
            });
        }
}
