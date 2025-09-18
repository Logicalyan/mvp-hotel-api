<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoomPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id',
        'weekday_price',
        'weekend_price',
        'currency',
        'start_date',
        'end_date',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
}
