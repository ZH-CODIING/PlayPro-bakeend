<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Http\Request;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */
    const ROLE_ADMIN       = 'Admin';
    const ROLE_OWNER       = 'Owner';
    const ROLE_COACH       = 'Coach';
    const ROLE_OWNER_ACADEMY   = 'OwnerAcademy';
    const ROLE_MANAGEMENT  = 'Management';
    const ROLE_USER        = 'User';

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'avatar',
        'status',
        'registration_role',
        'blocked',
    ];

    /*
    |--------------------------------------------------------------------------
    | Hidden
    |--------------------------------------------------------------------------
    */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'blocked' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // ملاعب هذا الـ Owner
    public function fields()
    {
        return $this->hasMany(Field::class, 'owner_id');
    }

    // الحجزات الخاصة بالمستخدم
    public function bookings()
    {
        return $this->hasMany(FieldBooking::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function rentalBookings()
    {
        return $this->hasMany(RentalBooking::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getTotalRenewalsAttribute()
    {
        return (int) $this->bookings()->sum('renewal_count');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers (Roles)
    |--------------------------------------------------------------------------
    */

    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }
    public function isOwnerAcademy()
    {
        return $this->role === self::ROLE_OWNER_ACADEMY;
    }
    public function isOwner()
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isCoach()
    {
        return $this->role === self::ROLE_COACH;
    }

    public function isManagement()
    {
        return $this->role === self::ROLE_MANAGEMENT;
    }

    public function isUser()
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasRole($roles)
    {
        return in_array($this->role, (array) $roles);
    }
    
    

  public function scopeFilter($query, Request $request)
{
    return $query
     ->when($request->filled('role'), function ($q) use ($request) {
                $q->where('role', $request->role);
     })
     
                ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);

            })
        ->when($request->filled('search'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('phone', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('email', 'LIKE', '%' . $request->search . '%');
            });
        });
}
    
}

