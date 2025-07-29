<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubCounty extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $table="sub_counties";
    protected $fillable=['name','county_id'];

    public function county()
    {
        return $this->belongsTo(Counties::class, 'county_id');
    }
    

}
