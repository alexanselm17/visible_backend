<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [

        'is_returned',
    ];

    /**
     * Get the invoices for the transaction.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'invoice_number');
    }

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class, 'transaction_id', 'id');
    }

    public function transactionProducts()
    {
        return $this->hasMany(TransactionProduct::class, 'transaction_id');
    }

    public function bankings()
    {
        return $this->hasMany(Banking::class, 'transaction_id');
    }

    public function salesPendings()
    {
        return $this->hasMany(SalesPending::class, 'invoice_number', 'id');
    }
}
