<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocumentRevision extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['identity_snapshot' => 'array', 'effective_on' => 'date', 'created_at' => 'datetime'];
}
