<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Models\RoomReservation;
use App\Models\ReservationPayment;
use App\Service\MidtransPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReservationPaymentController extends Controller
{
    use ApiResponses;

    protected MidtransPaymentService $service;

    public function __construct(MidtransPaymentService $service)
    {
        $this->service = $service;
    }

    public function midtransCallback(Request $request)
    {
        Log::info('MIDTRANS RAW CALLBACK', $request->all());

        /** ==========================================================
         * 1. FIX SIGNATURE (NORMALISASI GROSS AMOUNT)
         * ========================================================== */
        $serverKey = config('services.midtrans.server_key');

        // Normalisasi gross amount seperti Midtrans (tanpa koma/desimal)
        $gross = number_format($request->gross_amount, 0, '', '');

        $calculatedSignature = hash('sha512',
            $request->order_id .
            $request->status_code .
            $request->gross_amount .
            $serverKey
        );

        if ($calculatedSignature !== $request->signature_key) {
            Log::warning('MIDTRANS CALLBACK INVALID SIGNATURE', [
                'given' => $request->signature_key,
                'expected' => $calculatedSignature,
                'raw_gross' => $request->gross_amount,
                'normalized_gross' => $gross,
            ]);

            // Harus return 200 supaya Midtrans STOP retry
            return response()->json(['status' => 'ignored (invalid signature)'], 200);
        }

        /** ==========================================================
         * 2. Validasi status yang diterima
         * ========================================================== */
        $allowedStatus = ['settlement', 'capture', 'pending', 'deny', 'cancel', 'expire'];

        if (!in_array($request->transaction_status, $allowedStatus)) {
            return response()->json(['status' => 'ignored unknown status'], 200);
        }

        /** ==========================================================
         * 3. Cari reservasi
         * ========================================================== */
        $reservation = RoomReservation::where('reservation_code', $request->order_id)->first();

        if (!$reservation) {
            Log::error("Reservation not found for order_id: {$request->order_id}");
            return response()->json(['status' => 'ignored (reservation not found)'], 200);
        }

        /** ==========================================================
         * 4. Cegah duplikasi callback
         * ========================================================== */
        if (ReservationPayment::where('transaction_id', $request->transaction_id)->exists()) {
            return response()->json(['status' => 'duplicate callback (ignored)'], 200);
        }

        /** ==========================================================
         * 5. Proses pembayaran
         * ========================================================== */
        try {
            $payment = $this->service->handleCallback($request, $reservation);

            return $this->success($payment, "Successfull");

        } catch (\Exception $e) {
            Log::error('Midtrans callback processing error', [
                'error' => $e->getMessage()
            ]);

            // Tetap return 200 supaya Midtrans tidak spam
            return response()->json(['status' => 'processing failed (but acknowledged)'], 200);
        }
    }
}
