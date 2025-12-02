<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Models\RoomReservation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CheckInController extends Controller
{

    use ApiResponses;
    /**
     * Handle check-in for a reservation.
     */
    public function checkIn(Request $request, $id)
    {
        $reservation = RoomReservation::findOrFail($id);

        if ($reservation->reservation_status !== 'booked') {
            return $this->error('Reservation cannot be checked in.', 400);
        }

        if ($reservation->actual_check_in !== null) {
            return $this->error('Reservation already checked in.', 400);
        }

        // Ambil jam sekarang
        $actualCheckIn = now(); // 2025-12-03 13:22:00

        $reservation->update([
            'actual_check_in' => $actualCheckIn,
            'reservation_status' => 'checked_in'
        ]);

        $responseData = [
            'reservation_id'     => $reservation->id,
            'planned_check_in'   => $reservation->planned_check_in,
            'actual_check_in'    => $reservation->actual_check_in->format('Y-m-d H:i'),
            'status'             => $reservation->reservation_status
        ];

        return $this->success($responseData, 'Check-in successfully processed.', 200);
    }
}
