<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Models\RoomReservation;
use App\Services\LateCheckoutService;
use Illuminate\Http\Request;

class CheckOutController extends Controller
{
    use ApiResponses;
    protected $lateCheckoutService;

    public function __construct(LateCheckoutService $lateCheckoutService)
    {
        $this->lateCheckoutService = $lateCheckoutService;
    }

    /**
     * Handle room checkout request.
     */
    // public function checkOut(Request $request, $id)
    // {
    //     $request->validate([
    //         'actual_checkout_time' => ['required', 'date']
    //     ]);

    //     // Ambil reservasi
    //     $reservation = RoomReservation::findOrFail($id);

    //     // Pastikan belum checkout
    //     if ($reservation->actual_check_out !== null) {
    //         return response()->json([
    //             'message' => 'Reservation already checked out.'
    //         ], 400);
    //     }

    //     // Proses late checkout (menghitung + menyimpan fee)
    //     $result = $this->lateCheckoutService->processCheckout(
    //         $reservation,
    //         $request->actual_checkout_time
    //     );

    //     // Update status reservasi
    //     $reservation->update([
    //         'reservation_status' => 'checked_out'
    //     ]);

    //     return response()->json([
    //         'message' => 'Checkout processed successfully.',
    //         'reservation_id' => $reservation->id,
    //         'planned_check_out' => $reservation->planned_check_out,
    //         'actual_check_out' => $reservation->actual_check_out,
    //         'late' => $result['late'],
    //         'hours_late' => $result['hours_late'],
    //         'late_fee' => $result['fee'],
    //         'rule_applied' => $result['rule']->id ?? null
    //     ]);
    // }


    public function checkOut($id)
    {
        $reservation = RoomReservation::findOrFail($id);

        // Pastikan sudah check-in
        if ($reservation->actual_check_in === null) {
            return $this->error("Reservation has not been checked in yet.", 400);
        }

        // Pastikan belum checkout
        if ($reservation->actual_check_out !== null) {
            return $this->error("Reservation already checked out.", 400);
        }

        // Actual checkout time ← otomatis pakai waktu sekarang
        $actualCheckout = now();

        // Update reservation
        $reservation->update([
            'actual_check_out'   => $actualCheckout,
            'reservation_status' => 'checked_out'
        ]);

        // Update room menjadi available
        $room = $reservation->room;
        if ($room) {
            $room->update(['status' => 'available']);
        }

        return $this->success([
            'reservation_id'    => $reservation->id,
            'actual_check_out'  => $reservation->actual_check_out,
            'room_status'       => $room->status ?? null
        ], "Checkout processed successfully.");
    }
}
