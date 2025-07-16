<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RolesModel extends Model
{
    use HasFactory;
    protected $table="roles";
    protected $fillable=['name', 'slug', 'updated_at', 'created_at'];


    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($invoices) {
            $invoices->id = (string) Str::uuid();  // Automatically generate UUID
        });
    }
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }
}
