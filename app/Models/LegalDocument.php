<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['effective_on' => 'date', 'published_effective_on' => 'date', 'published_at' => 'datetime'];

    public function revisions(): HasMany
    {
        return $this->hasMany(LegalDocumentRevision::class);
    }
}
