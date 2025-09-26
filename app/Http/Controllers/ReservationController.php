<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Models\Reservation;
use App\Models\ReservationDetail;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReservationController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $reservations = Reservation::with(['details.room', 'details.roomType', 'hotel', 'user'])
            ->paginate(10);

        return $this->success($reservations, "Reservations fetched successfully", 200);
    }

    public function show($id)
    {
        $reservation = Reservation::with(['details.room', 'details.roomType', 'hotel', 'user'])
            ->find($id);

        if (!$reservation) {
            return $this->error("Reservation not found", 404);
        }

        return $this->success($reservation, "Reservation fetched successfully", 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'guest_count' => 'required|integer|min:1',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'responsible_name' => 'required|string|max:100',
            'responsible_email' => 'required|email',
            'responsible_phone' => 'required|string|max:20',
            'rooms' => 'required|array|min:1',
            'rooms.*.room_id' => 'required|exists:rooms,id',
            'payment_type' => 'required|in:full,dp', // full atau dp
        ]);

        return DB::transaction(function () use ($request) {
            $reservation = Reservation::create([
                'user_id' => Auth::id(),
                'hotel_id' => $request->hotel_id,
                'guest_count' => $request->guest_count,
                'check_in_date' => $request->check_in_date,
                'check_out_date' => $request->check_out_date,
                'responsible_name' => $request->responsible_name,
                'responsible_email' => $request->responsible_email,
                'responsible_phone' => $request->responsible_phone,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'total_price' => 0,
                'amount_paid' => 0,
            ]);

            $totalPrice = 0;
            $nights = Carbon::parse($request->check_in_date)->diffInDays(Carbon::parse($request->check_out_date));

            foreach ($request->rooms as $roomInput) {
                $room = Room::where('id', $roomInput['room_id'])
                    ->where('status', 'available')
                    ->first();

                if (!$room) {
                    throw new \Exception("Room ID {$roomInput['room_id']} is not available.");
                }

                $roomType = RoomType::findOrFail($room->room_type_id);
                if ($request->guest_count > $roomType->capacity) {
                    throw new \Exception("Guest count exceeds capacity for room type {$roomType->name}.");
                }

                $price = RoomPrice::where('room_type_id', $room->room_type_id)
                    ->where('start_date', '<=', $request->check_in_date)
                    ->where('end_date', '>=', $request->check_out_date)
                    ->first();

                if (!$price) {
                    throw new \Exception("No price available for room type {$roomType->name} in selected date range.");
                }

                $roomPrice = $price->weekday_price * $nights;
                $totalPrice += $roomPrice;

                ReservationDetail::create([
                    'reservation_id' => $reservation->id,
                    'room_type_id' => $room->room_type_id,
                    'room_id' => $room->id,
                    'price' => $roomPrice,
                ]);

                $room->update(['status' => 'occupied']);
            }

            // hitung pembayaran
            if ($request->payment_type === 'full') {
                $amountPaid = $totalPrice;
                $paymentStatus = 'paid';
                $status = 'confirmed';
            } else {
                $amountPaid = $totalPrice * 0.3;
                $paymentStatus = 'partial';
                $status = 'confirmed';
            }

            $reservation->update([
                'total_price' => $totalPrice,
                'amount_paid' => $amountPaid,
                'payment_status' => $paymentStatus,
                'status' => $status,
            ]);

            $reservation->load(['details.room', 'details.roomType', 'hotel', 'user']);

            return $this->success($reservation, "Reservation created successfully", 201);
        });
    }

    public function payRemaining($id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return $this->error("Reservation not found", 404);
        }

        if ($reservation->payment_status !== 'partial') {
            return $this->error("Reservation is not in partial payment status", 400);
        }

        $remainingAmount = $reservation->total_price - $reservation->amount_paid;

        $reservation->update([
            'amount_paid' => $reservation->total_price,
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        return $this->success([
            'reservation' => $reservation,
            'remaining_amount' => $remainingAmount,
        ], "Remaining payment completed successfully", 200);
    }


    public function update(Request $request, $id)
    {
        $reservation = Reservation::find($id);
        if (!$reservation) {
            return $this->error("Reservation not found", 404);
        }

        $reservation->update($request->only([
            'check_in_date',
            'check_out_date',
            'status',
            'payment_status',
        ]));

        return $this->success($reservation, "Reservation updated successfully", 200);
    }

    public function destroy($id)
    {
        $reservation = Reservation::find($id);
        if (!$reservation) {
            return $this->error("Reservation not found", 404);
        }

        $reservation->delete();

        return $this->success(null, "Reservation deleted successfully", 200);
    }
}
