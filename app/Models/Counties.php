<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Counties extends Model
{
    
protected $table="counties";
protected $fillable=['name','capital','code']; 
public function subCounties()
{
    return $this->hasMany(SubCounty::class, 'county_id');
}

use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;


}
