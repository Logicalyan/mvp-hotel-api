<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\RoomReservationService;
use App\Models\Room;
use App\Models\RoomReservation;
use Illuminate\Support\Facades\Auth;

class RoomReservationController extends Controller
{
    protected $service;

    public function __construct(RoomReservationService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of reservations
     */
    public function index(Request $request)
    {
        $query = RoomReservation::with(['room.roomType']);

        // Filter by user if authenticated
        // if ($request->user()) {
        //     $query->where('user_id', $request->user()->id);
        // }

        // Filter by status
        if ($request->has('status')) {
            $query->where('reservation_status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('check_in_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('check_out_date', '<=', $request->to_date);
        }

        $reservations = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $reservations
        ]);
    }

    /**
     * Calculate price before creating reservation
     */
    public function calculatePrice(Request $request)
    {
        $validated = $request->validate([
            'room_id'        => 'required|exists:rooms,id',
            'check_in_date'  => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        $room = Room::findOrFail($validated['room_id']);

        $calculation = $this->service->calculatePrice(
            $room->room_type_id,
            $validated['check_in_date'],
            $validated['check_out_date']
        );

        return response()->json([
            'success' => true,
            'data'    => $calculation
        ]);
    }

    /**
     * Store a newly created reservation
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_id'           => 'required|exists:rooms,id',
            'guest_name'        => 'required|string|max:255',
            'guest_phone'       => 'required|string|max:50',
            'guest_email'       => 'nullable|email|max:255',
            'check_in_date'     => 'required|date',
            'check_out_date'    => 'required|date|after:check_in_date',
            'planned_chec   k_in'  => 'nullable|date',
            'planned_check_out' => 'nullable|date',
        ]);

        // Check room availability
        $room = Room::findOrFail($validated['room_id']);

        // Optional: Add availability check logic here
        // $this->checkRoomAvailability($room->id, $validated['check_in_date'], $validated['check_out_date']);

        // Add authenticated user ID if available
        $data = $validated;
        // if (Auth::check()) {
        //     $data['user_id'] = Auth::id();
        // }

        try {
            $reservation = $this->service->createReservation($data);

            return response()->json([
                'success'     => true,
                'message'     => 'Reservation created successfully.',
                'reservation' => $reservation->load(['room.roomType', 'prices']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reservation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified reservation
     */
    public function show($id)
    {
        $reservation = RoomReservation::with(['room.roomType', 'prices'])
            ->findOrFail($id);

        // Optional: Add authorization check
        // if (Auth::check() && $reservation->user_id !== Auth::id()) {
        //     return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        // }

        return response()->json([
            'success' => true,
            'data'    => $reservation
        ]);
    }

    /**
     * Update reservation status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'reservation_status' => 'required|in:booked,confirmed,checked_in,checked_out,cancelled',
            'notes' => 'nullable|string',
        ]);

        $reservation = RoomReservation::findOrFail($id);
        $reservation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Reservation status updated successfully.',
            'data'    => $reservation
        ]);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_status' => 'required|in:pending,paid,partially_paid,refunded',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        $reservation = RoomReservation::findOrFail($id);
        $reservation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully.',
            'data'    => $reservation
        ]);
    }

    /**
     * Cancel reservation
     */
    public function cancel($id)
    {
        $reservation = RoomReservation::findOrFail($id);

        // Check if reservation can be cancelled
        if (in_array($reservation->reservation_status, ['checked_out', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel this reservation.'
            ], 422);
        }

        $reservation->update([
            'reservation_status' => 'cancelled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reservation cancelled successfully.',
            'data'    => $reservation
        ]);
    }

    /**
     * Check room availability for given dates
     */
    private function checkRoomAvailability($roomId, $checkIn, $checkOut)
    {
        $conflicts = RoomReservation::where('room_id', $roomId)
            ->where('reservation_status', '!=', 'cancelled')
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                    ->orWhere(function ($q) use ($checkIn, $checkOut) {
                        $q->where('check_in_date', '<=', $checkIn)
                          ->where('check_out_date', '>=', $checkOut);
                    });
            })
            ->exists();

        if ($conflicts) {
            abort(422, 'Room is not available for the selected dates.');
        }
    }
}
