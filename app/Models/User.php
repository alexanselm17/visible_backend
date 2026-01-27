<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

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
        'is_logged_in',
        'card_number',
        'occupation',
        'gender',
        'fcm_token',
        'county_id',
        'subcounty_id',
        'referal_code',
        'my_code',

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
        if (! empty($value)) {
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
        if (! is_array($roles)) {
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

    public function county()
    {
        return $this->belongsTo(Counties::class);
    }

    public function subCounty()
    {
        return $this->belongsTo(SubCounty::class, 'subcounty_id');
    }

    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->unreadNotifications()->count();
    }

    public function screenshots()
    {
        return $this->hasMany(Screenshots::class, 'processed_by')
            ->orderBy('timestamp', 'desc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get recent screenshots (last 30 days)
     */
    public function recentScreenshots()
    {
        return $this->screenshots()
            ->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * Get popular screenshots (with views > 0)
     */
    public function popularScreenshots()
    {
        return $this->screenshots()
            ->where('views', '>', 0)
            ->orderBy('views', 'desc');
    }

    public function referredUsers()
    {
        return $this->hasMany(User::class, 'my_code', 'referral_code')
            ->where('id', '!=', $this->id);
    }

    public function fraudFlags()
    {
        return $this->hasMany(\App\Models\UserFraud::class, 'user_id', 'id');
    }

    public function latestFraudFlag()
    {
        return $this->hasOne(\App\Models\UserFraud::class, 'user_id', 'id')
            ->latestOfMany('flagged_at'); // or latestOfMany() if using id
    }


    public function campaignOwnerProfile()
    {
        return $this->hasOne(\App\Models\CampaignOwnerProfile::class, 'user_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(\App\Models\UserSubscription::class, 'user_id');
    }

    public function activeSubscription()
    {
        return $this->hasOne(\App\Models\UserSubscription::class, 'user_id')
            ->where('status', 'ACTIVE')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->latest('starts_at');
    }
}
