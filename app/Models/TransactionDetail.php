<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class TransactionDetail extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trans_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'transaction_type',
        'transaction_id',
        'processed_by',
        'gross_total',

        "approval_status",
        'approved_by'

    ];
      protected $casts = [
        'gross_total' => 'decimal:2',
    ];

    /**
     * Get the transaction associated with the detail.
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the user who processed the detail.
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }



    /**
     * Get the transaction product.
     */
    public function transactionProduct()
    {
        return $this->hasOne(TransactionProduct::class, 'transaction_id', 'transaction_id');
    }
}
