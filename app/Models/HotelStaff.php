<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// HotelStaff Model
class HotelStaff extends Model
{
    protected $fillable = [
        'user_id', 'hotel_id', 'position'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    // Constants untuk position
    const POSITION_HOTEL_ADMIN = 'hotel_admin';
    const POSITION_RECEPTIONIST = 'receptionist';
    const POSITION_HOUSEKEEPING = 'housekeeping';
    const POSITION_MANAGER = 'manager';

    public static function positions()
    {
        return [
            self::POSITION_HOTEL_ADMIN,
            self::POSITION_RECEPTIONIST,
            self::POSITION_HOUSEKEEPING,
            self::POSITION_MANAGER,
        ];
    }
}
