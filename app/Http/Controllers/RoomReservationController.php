<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Filters\ReservationFilter;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use App\Services\RoomReservationService;
use App\Models\Room;
use App\Models\RoomReservation;
use Illuminate\Support\Facades\Auth;

class RoomReservationController extends Controller
{
    use ApiResponses;
    protected $service;

    public function __construct(RoomReservationService $service)
    {
        $this->service = $service;
    }

    /**
     * Get reservations by hotel_id
     * GET /api/hotel/{hotel_id}/reservations
     */
    public function indexByHotelId(Request $request, ReservationFilter $filters, $hotel_id)
    {
        // 1. Validasi hotel exists
        Hotel::findOrFail($hotel_id);

        // 2. Base query: hanya reservasi dari kamar yang termasuk hotel ini
        $baseQuery = RoomReservation::query()
            ->with(['room.roomType'])
            ->whereHas('room.roomType', function ($q) use ($hotel_id) {
                $q->where('hotel_id', $hotel_id);
            });

        // 3. Apply filters
        $query = $filters->apply($baseQuery);

        // 4. Pagination
        $perPage = $request->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);

        $reservations = $query->paginate($perPage);

        // 5. Response
        return $this->success(
            $reservations,
            "Daftar reservasi hotel berhasil diambil",
            200
        );
    }

    public function showByHotelId($hotel_id, $reservation_id)
    {
        Hotel::findOrFail($hotel_id);

        $reservation = RoomReservation::with([
            'room.roomType',
            'prices' => fn($q) => $q->orderBy('date')
        ])
            ->whereHas('room.roomType', fn($q) => $q->where('hotel_id', $hotel_id))
            ->findOrFail($reservation_id);

        return $this->success($reservation, "Detail reservasi berhasil diambil");
    }

    // RoomReservationController.php
    public function storeByHotelId(Request $request, $hotel_id)
    {
        Hotel::findOrFail($hotel_id); // validasi hotel exists

        $validated = $request->validate([
            'room_id'           => 'required|exists:rooms,id',
            'guest_name'        => 'required|string|max:255',
            'guest_phone'       => 'required|string|max:50',
            'guest_email'       => 'nullable|email|max:255',
            'check_in_date'     => 'required|date|after_or_equal:today',
            'check_out_date'    => 'required|date|after:check_in_date',
            'planned_check_in'  => 'nullable|date_format:H:i',
            'planned_check_out' => 'nullable|date_format:H:i',
        ]);

        // Pastikan room milik hotel ini
        $room = Room::where('id', $validated['room_id'])
            ->whereHas('roomType', fn($q) => $q->where('hotel_id', $hotel_id))
            ->firstOrFail();

        $data = $validated;
        $data['room_id'] = $room->id;

        try {
            $reservation = $this->service->createReservation($data);

            return $this->success(
                $reservation->load(['room.roomType', 'prices']),
                "Reservasi berhasil dibuat",
                201
            );
        } catch (\Exception $e) {
            return $this->error("Gagal membuat reservasi: " . $e->getMessage(), 500);
        }
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
             'room_type_id' => 'required_without:room_id|exists:room_types,id',
    'room_id' => 'required_without:room_type_id|exists:rooms,id',
            'guest_name'        => 'required|string|max:255',
            'guest_phone'       => 'required|string|max:50',
            'guest_email'       => 'nullable|email|max:255',
            'check_in_date'     => 'required|date',
            'check_out_date'    => 'required|date|after:check_in_date',
            'planned_check_in'  => 'nullable|date',
            'planned_check_out' => 'nullable|date',
        ]);

        // Check room availability

        // Optional: Add availability check logic here
        // $this->checkRoomAvailability($room->id, $validated['check_in_date'], $validated['check_out_date']);

        // Add authenticated user ID if available
        $data = $validated;
        // if (Auth::check()) {
        //     $data['user_id'] = Auth::id();
        // }

        try {
            $reservation = $this->service->createReservationUser($data);

            return response()->json([
                'success'     => true,
                'message'     => 'Reservation created successfully.',
                'data' => $reservation->load(['room.roomType', 'prices']),
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
    try {
        $reservation = RoomReservation::with([
            'user',
            'roomType' => function($query) {
                $query->with(['hotel', 'facilities', 'images', 'prices']);
            },
            'room'
        ])->findOrFail($id);

        return $this->success($reservation, "Reservation found successfully", 200);
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return $this->error("Reservation not found", 404);
    } catch (\Exception $e) {
        return $this->error("Failed to fetch reservation: " . $e->getMessage(), 500);
    }
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

        // 1. Tidak bisa cancel kalau sudah check out atau sudah cancel
        if (in_array($reservation->reservation_status, ['checked_out', 'cancelled'])) {
            return $this->error("Cannot cancel this reservation.", 422);
        }

        // 2. Tidak bisa cancel jika sudah dibayar (untuk sekarang)
        if ($reservation->payment_status === 'paid') {
            return $this->error("This reservation is paid. Refund feature is required before cancellation.", 422);
        }

        // 3. Release room availability
        Room::where('id', $reservation->room_id)->update([
            "status" => "available"
        ]);

        // 4. Update reservation
        $reservation->update([
            'reservation_status' => 'cancelled',
            'cancelled_at'       => now(),
            'payment_status'     => 'failed', // atau pending tetap
        ]);

        return $this->success($reservation, "Cancelled Reservation Successfully");
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

    //reservation by user ID
    public function reservationByUserId()
    {
        $id = Auth::id();
        $reservations = RoomReservation::where('user_id', $id)->get();
        return $this->success(
            $reservations,
            "Daftar reservasi user berhasil diambil",
            200
        );
    }
}
