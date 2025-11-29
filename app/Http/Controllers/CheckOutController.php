<?php

namespace App\Http\Controllers;

use App\Models\RoomReservation;
use App\Services\LateCheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    protected $lateCheckoutService;

    public function __construct(LateCheckoutService $lateCheckoutService)
    {
        $this->lateCheckoutService = $lateCheckoutService;
    }

    /**
     * Handle room checkout request.
     */
    public function checkout(Request $request, $id)
    {
        $request->validate([
            'actual_checkout_time' => ['required', 'date']
        ]);

        // Ambil reservasi
        $reservation = RoomReservation::findOrFail($id);

        // Pastikan belum checkout
        if ($reservation->actual_check_out !== null) {
            return response()->json([
                'message' => 'Reservation already checked out.'
            ], 400);
        }

        // Proses late checkout (menghitung + menyimpan fee)
        $result = $this->lateCheckoutService->processCheckout(
            $reservation,
            $request->actual_checkout_time
        );

        // Update status reservasi
        $reservation->update([
            'reservation_status' => 'checked_out'
        ]);

        return response()->json([
            'message' => 'Checkout processed successfully.',
            'reservation_id' => $reservation->id,
            'planned_check_out' => $reservation->planned_check_out,
            'actual_check_out' => $reservation->actual_check_out,
            'late' => $result['late'],
            'hours_late' => $result['hours_late'],
            'late_fee' => $result['fee'],
            'rule_applied' => $result['rule']->id ?? null
        ]);
    }
}
