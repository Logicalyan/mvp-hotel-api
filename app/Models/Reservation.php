<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'hotel_id',
        'guest_count',
        'check_in_date',
        'check_out_date',
        'responsible_name',
        'responsible_email',
        'responsible_phone',
        'status',
        'total_price',
        'payment_status'
    ];

    public function details()
    {
        return $this->hasMany(ReservationDetail::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
