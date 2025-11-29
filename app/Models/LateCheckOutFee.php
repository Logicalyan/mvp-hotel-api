<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LateCheckoutFee extends Model
{
    protected $fillable = [
        'reservation_id',
        'amount',
        'hours_late',
        'description',
    ];

    public function reservation()
    {
        return $this->belongsTo(RoomReservation::class, 'reservation_id');
    }
}
