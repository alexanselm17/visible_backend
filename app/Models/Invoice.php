<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    // App\Models\Invoice.php

    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

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
