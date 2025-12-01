<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    //Relations with roles
    public function roles() {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole($role) {
        return $this->roles()->where("slug", $role)->exists();
    }

    // Hotel staff positions
    public function hotelStaff()
    {
        return $this->hasMany(HotelStaff::class);
    }

    public function hotels()
    {
        return $this->belongsToMany(Hotel::class, 'hotel_staff')
                    ->withPivot('position')
                    ->withTimestamps();
    }

    public function isCustomer()
    {
        return $this->roles->contains('name', 'customer');
    }

    public function isSuperAdmin()
    {
        return $this->roles->contains('name', 'admin');
    }

    public function hasPositionInHotel($position, $hotelId)
    {
        return $this->hotelStaff()
                    ->where('hotel_id', $hotelId)
                    ->where('position', $position)
                    ->exists();
    }

    public function isStaffOfHotel($hotelId)
    {
        return $this->hotelStaff()
                    ->where('hotel_id', $hotelId)
                    ->exists();
    }

    public function positionInHotel($hotelId)
    {
        $staff = $this->hotelStaff()
                      ->where('hotel_id', $hotelId)

                      ->first();

        return $staff?->position;
    }
}
