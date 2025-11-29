<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoomReservationPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'date',
        'price',
    ];

    protected $casts = [
        'date' => 'date',
        'price' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function reservation()
    {
        return $this->belongsTo(RoomReservation::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    public function getFormattedPrice(): string
    {
        return number_format($this->price, 0, ',', '.');
    }
}
