<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesPending extends Model
{

  protected $fillable = [
    'invoice_number',
    'type',
    'amount',
    'customer_id',
    'customer_balance',
    'invoice_note',
    'posted_by',
    'banking',

  ];
  protected $table="sales_pending";
}
