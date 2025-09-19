<?php

// app/Models/Room.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'hotel_id',
        'room_number',
        'floor',
        'status',
        'is_active',
        'room_type_id'
    ];

    // relasi ke Hotel
    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    // relasi ke RoomType
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
}
