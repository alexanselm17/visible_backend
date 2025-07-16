<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductsModel extends Model{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'category',
        'selling_price',
        'parent_id',
        'unit',
        'unit_name',
        'min_stock',

    ];


    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($product) {
            $product->id = (string) Str::uuid();  // Automatically generate UUID
        });
    }

   protected $casts = [
        'buying_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];







}
