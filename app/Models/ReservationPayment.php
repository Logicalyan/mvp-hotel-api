<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationPayment extends Model
{
    protected $fillable = [
        'reservation_id',
        'payment_type',
        'transaction_id',
        'fraud_status',
        'gross_amount',
        'bank',
        'va_number',
        'qr_reference',
        'status',
    ];

    public function reservation()
    {
        return $this->belongsTo(RoomReservation::class);
    }

    protected static function booted()
    {
        static::created(function ($payment) {
            if ($payment->status === 'paid') {
                $payment->reservation->update([
                    'payment_status'     => 'paid',
                    'reservation_status' => 'booked',
                ]);
            }
        });
    }
}
