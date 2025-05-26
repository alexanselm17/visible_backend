<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invoice extends Model
{
  use HasFactory;

  protected $fillable = [
    'type',
    'amount',
    'processed_by',
    'customer_balance',
    'posted_by',
    'advert_id',
    'banking',
  ];



  /**
   * Get the transaction associated with the invoice.
   */
  public function transaction(): BelongsTo
  {
    return $this->belongsTo(Transaction::class, 'invoice_number');
  }

  /**
   * Get the customer associated with the invoice.
   */
  public function customer(): BelongsTo
  {
    return $this->belongsTo(Customers::class, 'customer_id');
  }

  /**
   * Get the user who posted the invoice.
   */
  public function postedBy(): BelongsTo
  {
    return $this->belongsTo(User::class, 'posted_by');
  }

  /**
   * Get the banking associated with the invoice.
   */
  public function banking(): BelongsTo
  {
    return $this->belongsTo(Banking::class, 'banking_id');
  }

  /**
   * Get the payment method name
   */
  public function getPaymentMethodAttribute(): string
  {
    return $this->banking?->name ?? 'Cash';
  }
}
