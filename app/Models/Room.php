<?php

// app/Models/Room.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        // 'hotel_id',
        'room_number',
        'floor',
        'status',
        'is_active',
        'room_type_id'
    ];

    // relasi ke RoomType
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    // App\Models\Room.php
    public function reservations()
    {
        return $this->hasMany(RoomReservation::class);
    }
}
