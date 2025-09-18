<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'capacity',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function prices()
    {
        return $this->hasMany(RoomPrice::class);
    }

    public function facilities()
    {
        return $this->belongsToMany(RoomTypeFacility::class, 'facility_room_types', 'room_type_id', 'room_type_facility_id');
    }

        public function images()
    {
        return $this->hasMany(RoomTypeImage::class);
    }

    public function beds()
    {
        return $this->hasMany(RoomTypeBed::class);
    }
}
