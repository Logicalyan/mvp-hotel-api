<?php

namespace App\Http\Controllers;

use App\Models\RoomReservation;
use Illuminate\Support\Facades\Log;
use Midtrans\Snap;

class MidtransController extends Controller
{
    public function createSnapToken($hotel_id, $reservation_id)
    {
        try {
            // Eager load relasi yang dibutuhkan
            $reservation = RoomReservation::with(['roomType', 'room'])->findOrFail($reservation_id);

            // CEK HOTEL VIA roomType (karena kamu punya room_type_id langsung!)
            if ($reservation->roomType->hotel_id != $hotel_id) {
                return response()->json(['error' => 'Akses ditolak: Hotel tidak cocok'], 403);
            }

            // Cek status pembayaran
            if ($reservation->payment_status !== 'pending') {
                return response()->json(['error' => 'Reservasi sudah dibayar atau dibatalkan'], 400);
            }

            // Setup Midtrans
            \Midtrans\Config::$serverKey = config('services.midtrans.server_key');
            \Midtrans\Config::$isProduction = config('services.midtrans.is_production', false);
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            $params = [
                'transaction_details' => [
                    'order_id'     => $reservation->reservation_code,
                    'gross_amount' => $reservation->total_price,
                ],
                'customer_details' => [
                    'first_name' => $reservation->guest_name ?? 'Tamu',
                    'phone'      => $reservation->guest_phone,
                    'email'      => $reservation->guest_email ?? 'noemail@hotel.com',
                ],
                'item_details' => [
                    [
                        'id'       => 'RES-' . $reservation->id,
                        'price'    => $reservation->total_price,
                        'quantity' => 1,
                        'name'     => "Kamar {$reservation->roomType->name} - {$reservation->nights} malam",
                    ]
                ],
                'callbacks' => [
                    'finish' => url("/hotel/{$hotel_id}/reservations/{$reservation->id}")
                ]
            ];

            $snapToken = Snap::getSnapToken($params);

            return response()->json([
                'snap_token' => $snapToken
            ]);

        } catch (\Exception $e) {
            Log::error('Midtrans Token Error: ' . $e->getMessage(), [
                'hotel_id' => $hotel_id,
                'reservation_id' => $reservation_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Gagal membuat token pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }
}
