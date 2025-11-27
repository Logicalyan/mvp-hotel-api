<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoomTypeFacility extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function roomTypes()
    {
        return $this->belongsToMany(RoomType::class, 'facility_room_types', 'room_type_facility_id', 'room_type_id');
    }
}
