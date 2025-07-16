<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class TransactionProduct extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tran_products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'product_id',
        'price',
        'quantity',
        'total',
        'discount'
    ];


     protected $casts = [
        'price' => 'decimal:2',  // Ensure buying_price is cast to decimal with 2 decimal places
        'quantity' => 'decimal:2',
        'total' => 'decimal:2', // Ensure selling_price is cast to decimal with 2 decimal places
    ];

    /**
     * Get the transaction that owns the transaction product.
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the product that is part of the transaction product.
     */
    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id');
    }

}
