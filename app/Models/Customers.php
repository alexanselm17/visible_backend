<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Customers extends Model
{
  use HasFactory;

  protected $fillable = [
    'name',
    'phone',

  ];


  public $incrementing = false;
  protected $keyType = 'string';

  protected static function booted()
  {
    static::creating(function ($customer) {
      $customer->id = (string) Str::uuid();
    });
  }


  public function invoices(): HasMany
  {
    return $this->hasMany(Invoice::class, 'customer_id');
  }

  public function invoiceLatest(): HasMany
  {
    return $this->hasMany(Invoice::class, 'customer_id')->latest()->limit(1);
  }


  public function latestInvoice(): BelongsTo
  {
    return $this->belongsTo(Invoice::class, 'id', 'customer_id')
      ->latest();
  }

  public function getCurrentBalanceAttribute(): float
  {
    return $this->invoices()
      ->latest()
      ->first()
      ?->customer_balance ?? 0;
  }
}
