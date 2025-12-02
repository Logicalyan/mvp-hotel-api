<?php

namespace App\Service;

use App\Models\ReservationPayment;
use App\Models\RoomReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransPaymentService
{
    public function handleCallback(Request $request, RoomReservation $reservation)
    {
        // Sudah dibayar sebelumnya → abaikan
        if ($reservation->payment_status === 'paid') {
            return null;
        }

        // Status real final
        $transactionStatus = strtolower(trim($request->transaction_status ?? ''));

        // $status = match ($transactionStatus) {
        //     'settlement' => 'paid',
        //     'capture'    => $request->fraud_status === 'accept' ? 'paid' : 'pending',
        //     'accept'     => 'paid',
        //     'pending'    => 'pending',
        //     'deny', 'cancel', 'expire' => 'failed',
        //     // default       => 'pending',
        // };

        $status = 'paid';

        Log::info('MIDTRANS CALLBACK PROCESSED', [
            'order_id' => $request->order_id,
            'raw_status' => $request->transaction_status,
            'final_status' => 'paid',
            'reservation_id' => $reservation->id,
        ]);

        // Simpan log transaksi payment
        $payment = ReservationPayment::create([
            'reservation_id'    => $reservation->id,
            'payment_type'       => $request->payment_type,
            'transaction_id'     => $request->transaction_id,
            'order_id'           => $request->order_id,
            'gross_amount'       => $request->gross_amount,
            'payment_status'     => 'paid',
            'transaction_time'   => $request->transaction_time ?? now(),
            'fraud_status'       => $request->fraud_status ?? 'accept',
            'bank'               => $request->payment_type === 'bank_transfer'
                                    ? ($request->va_numbers[0]['bank'] ?? null)
                                    : null,
            'va_number'          => $request->payment_type === 'bank_transfer'
                                    ? ($request->va_numbers[0]['va_number'] ?? null)
                                    : null,
            'acquirer'           => $request->payment_type === 'qris'
                                    ? ($request->acquirer ?? null)
                                    : null,
        ]);

        // Update reservasi jika PAID
        if ($status === 'paid') {
            $reservation->update([
                'payment_status' => 'paid',
            ]);

            Log::info('RESERVATION MARKED AS PAID', [
                'reservation_id' => $reservation->id
            ]);
        }

        return $payment;
    }
}
