<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
  use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

  public function canAccessPanel(Panel $panel): bool
  {
    return $this->isAdmin() || $this->isDeveloper() || $this->isManager();
  }

  public function getUserName(): string
  {
    return $this->fullname;
  }



  public function permissions()
  {
    return $this->belongsToMany(Permission::class, 'user_permission', 'user_id', 'permission_id');
  }


  public function hasPermission($permissionSlug)
  {
    if ($this->permissions->contains('slug', $permissionSlug)) {
      return true;
    }
  }


  protected $fillable = [
    'fullname',
    'username',
    'phone',
    'is_active',
    'role_id',
    'email',
    'is_verified',
    'password',
    'national_id',
    'is_logged_in',
    'card_number',
    'occupation',
    'location',
    'gender',
    'fcm_token',
    'town',
    'estate',
    'county'


  ];


  public function user()
  {
    return $this->belongsTo(User::class, 'processed_by');
  }

  public $incrementing = false;
  protected $keyType = 'string';

  protected static function booted()
  {
    static::creating(function ($transProduct) {
      $transProduct->id = (string) Str::uuid();  // Automatically generate UUID
    });
  }

  protected $hidden = [
    'password',
    'remember_token',
  ];

  protected $casts = [
    'email_verified_at' => 'datetime',
  ];

  /**
   * Set the user's password, ensuring it's hashed.
   *
   * @param  string  $value
   * @return void
   */
  public function setPasswordAttribute($value)
  {
    // Only hash if the password is not already hashed
    if (!empty($value)) {
      $this->attributes['password'] = Hash::make($value);
    }
  }

  // Relationships
  public function role()
  {
    return $this->belongsTo(RolesModel::class);
  }





  public function processedBankings()
  {
    return $this->hasMany(Banking::class, 'processed_by');
  }

  public function approvedBankings()
  {
    return $this->hasMany(Banking::class, 'approved_by');
  }

  public function postedInvoices()
  {
    return $this->hasMany(Invoice::class, 'posted_by');
  }

  /**
   * Determine if the user is an admin based on their role.
   *
   * @return bool
   */

  public function isAdmin(): bool
  {
    return $this->role?->slug === 'admin';
  }
  public function isDeveloper(): bool
  {
    return $this->role?->slug === 'dev';
  }

  public function isManager(): bool
  {
    return $this->role?->slug === 'manager';
  }

  public function isCashier(): bool
  {
    return $this->role?->slug === 'cashier';
  }

  public function isCustomerChampion(): bool
  {
    return $this->role?->slug === 'salesman';
  }

  public function hasRole($roles): bool
  {
    if (!is_array($roles)) {
      $roles = [$roles];
    }

    return $this->role && in_array($this->role->slug, $roles);
  }








  // public function hasPermission($permissionSlug)
  // {
  //   // If user is admin or developer, they have all permissions
  //   if ($this->isAdmin() || $this->isDeveloper()) {
  //     return true;
  //   }

  //   // Check user-specific permission
  //   if ($this->permissions->contains('slug', $permissionSlug)) {
  //     return true;
  //   }

  //   // Check role-based permission
  //   return $this->role?->permissions->contains('slug', $permissionSlug) ?? false;
  // }

  public function hasAnyPermission(array $permissions)
  {
    return collect($permissions)->some(fn($permission) => $this->hasPermission($permission));
  }

  public function hasAllPermissions(array $permissions)
  {
    return collect($permissions)->every(fn($permission) => $this->hasPermission($permission));
  }

  /**
   * Get all permissions for the user (combining role and direct permissions)
   */
  public function getAllPermissions()
  {
    $rolePermissions = $this->role?->permissions ?? collect();
    $directPermissions = $this->permissions;

    return $rolePermissions->merge($directPermissions)->unique('id');
  }

  /**
   * Get permissions by category
   */
  public function getPermissionsByCategory($category)
  {
    return $this->getAllPermissions()->where('category', $category);
  }


  public function notifications()
  {
    return $this->hasMany(Notification::class)->orderBy('created_at', 'desc');
  }

  public function unreadNotifications()
  {
    return $this->hasMany(Notification::class)->where('is_read', false)->orderBy('created_at', 'desc');
  }
  /**
   * Get the count of unread notifications.
   */
  public function getUnreadNotificationsCountAttribute(): int
  {
    return $this->unreadNotifications()->count();
  }
}
