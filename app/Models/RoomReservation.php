<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoomReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_type_id',
        'room_id',
        'reservation_code',

        // date (perhitungan nights)
        'check_in_date',
        'check_out_date',
        'nights',

        // planned times
        'planned_check_in',
        'planned_check_out',

        // actual real times
        'actual_check_in',
        'actual_check_out',

        // guest data
        'guest_name',
        'guest_phone',
        'guest_email',

        // financial
        'total_price',
        'payment_status',
        'reservation_status',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'planned_check_in' => 'datetime',
        'planned_check_out' => 'datetime',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'total_price' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function prices()
    {
        return $this->hasMany(RoomReservationPrice::class, 'reservation_id');
    }

    public function lateCheckoutFee()
    {
        return $this->hasOne(LateCheckoutFee::class, 'reservation_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->reservation_status === 'cancelled';
    }

    public function nightsCount(): int
    {
        return $this->nights;
    }

    public function getFormattedTotalPrice(): string
    {
        return number_format($this->total_price, 0, ',', '.');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeActive($query)
    {
        return $query->where('reservation_status', 'booked');
    }

    public function scopeByRoom($query, $roomId)
    {
        return $query->where('room_id', $roomId);
    }
}
