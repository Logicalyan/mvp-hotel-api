<?php

namespace App\Http\Controllers;

use App\Models\RoomReservation;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    /**
     * Handle check-in for a reservation.
     */
    public function checkIn(Request $request, $id)
    {
        $request->validate([
            'actual_check_in_time' => ['required', 'date']
        ]);

        // Ambil data reservation
        $reservation = RoomReservation::findOrFail($id);

        // Pastikan statusnya masih "booked"
        if ($reservation->reservation_status !== 'booked') {
            return response()->json([
                'message' => 'Reservation cannot be checked in.'
            ], 400);
        }

        // Pastikan belum pernah check-in
        if ($reservation->actual_check_in !== null) {
            return response()->json([
                'message' => 'Reservation already checked in.'
            ], 400);
        }

        // Update check-in time
        $reservation->update([
            'actual_check_in' => $request->actual_check_in_time,
            'reservation_status' => 'checked_in'
        ]);

        return response()->json([
            'message' => 'Check-in successfully processed.',
            'reservation_id' => $reservation->id,
            'planned_check_in' => $reservation->planned_check_in,
            'actual_check_in' => $reservation->actual_check_in,
            'status' => $reservation->reservation_status
        ]);
    }
}
