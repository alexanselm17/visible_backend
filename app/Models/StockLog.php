<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class StockLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'drum_id',
        'curr_stock',
        'prev_stock',
        'quantity',
        'station_id',
        'product_id',

    ];

    /**
     * Get the drum that owns the stock log.
     */
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($stockLog) {
            $stockLog->id = (string) Str::uuid();  // Automatically generate UUID
        });
    }
}
