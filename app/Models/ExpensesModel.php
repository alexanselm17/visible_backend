<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpensesModel extends Model
{
    use HasFactory;

    protected $table = "expenses";

    protected $fillable = [
        'description',
        'amount',
        'posted_by',
        'approved_by',
        'approval_status',

    ];

    /**
     * Relationships
     */
    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }


}
