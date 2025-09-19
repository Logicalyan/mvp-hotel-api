<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoomTypeImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id',
        'image_url',
        'is_primary'
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
}
