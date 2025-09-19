<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BedType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function roomTypeBeds()
    {
        return $this->hasMany(RoomTypeBed::class);
    }
}
