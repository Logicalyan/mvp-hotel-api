<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomPrice;
use App\Models\RoomReservation;
use App\Models\RoomReservationPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use function Symfony\Component\Clock\now;

class RoomReservationService
{
    /**
     * CEK KETERSEDIAAN KAMAR
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

    /**
     * AUTO-SELECT ROOM YANG TERSEDIA BERDASARKAN ROOM TYPE
     */
    private function findAvailableRoom($roomTypeId, $checkIn, $checkOut)
    {
        // Ambil semua room dengan room_type_id yang diminta
        $rooms = Room::where('room_type_id', $roomTypeId)
            ->where('status', 'available')
            ->get();

        if ($rooms->isEmpty()) {
            abort(422, 'No rooms available for this room type.');
        }

        // Cari room yang tidak bentrok dengan reservasi existing
        foreach ($rooms as $room) {
            $hasConflict = RoomReservation::where('room_id', $room->id)
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

            // Jika tidak ada konflik, return room ini
            if (!$hasConflict) {
                return $room;
            }
        }

        // Jika semua room bentrok
        abort(422, 'No available rooms for the selected dates.');
    }


    /**
     * HITUNG HARGA
     */
    public function calculatePrice($roomTypeId, $checkIn, $checkOut)
    {
        $start = Carbon::parse($checkIn);
        $end = Carbon::parse($checkOut);

        $dates = [];
        $total = 0;

        while ($start < $end) {
            $date = $start->copy();

            $price = $this->getPriceForDate($roomTypeId, $date);

            $dates[] = [
                'date' => $date->toDateString(),
                'price' => $price,
            ];

            $total += $price;
            $start->addDay();
        }

        return [
            'nights'   => count($dates),
            'total_price' => $total,
            'breakdown' => $dates,
        ];
    }

    private function getPriceForDate($roomTypeId, Carbon $date)
    {
        $seasonal = RoomPrice::where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if ($seasonal) {
            return $this->pickWeekdayOrWeekend($seasonal, $date);
        }

        $default = RoomPrice::where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->whereNull('start_date')
            ->whereNull('end_date')
            ->first();

        return $this->pickWeekdayOrWeekend($default, $date);
    }

    private function pickWeekdayOrWeekend(RoomPrice $price, Carbon $date)
    {
        return $date->isWeekend()
            ? $price->weekend_price
            : $price->weekday_price;
    }


    /**
     * CREATE RESERVATION (WITH AVAILABILITY CHECK)
     */
    public function createReservation(array $data)
    {
        return DB::transaction(function () use ($data) {

            /**
             * 1. Jika room_id tidak ada, auto-select berdasarkan room_type_id
             */
            if (empty($data['room_id'])) {
                if (empty($data['room_type_id'])) {
                    abort(422, 'Either room_id or room_type_id is required.');
                }

                $room = $this->findAvailableRoom(
                    $data['room_type_id'],
                    $data['check_in_date'],
                    $data['check_out_date']
                );
            } else {
                // Jika room_id sudah ada, ambil room tersebut
                $room = Room::with('roomType')->findOrFail($data['room_id']);
                
                // CEK KAMAR TERSEDIA
                $this->checkRoomAvailability(
                    $room->id,
                    $data['check_in_date'],
                    $data['check_out_date']
                );
            }

            $roomTypeId = $room->room_type_id;

            /**
             * 2. Hitung harga
             */
            $calc = $this->calculatePrice(
                $roomTypeId,
                $data['check_in_date'],
                $data['check_out_date']
            );

            $paymentDue = Carbon::now()->addMinutes(30);

            /**
             * 3. Gabungkan jam planned check in/out
             */
            $checkInDate  = Carbon::parse($data['check_in_date']);
            $checkOutDate = Carbon::parse($data['check_out_date']);

            $plannedCheckIn = !empty($data['planned_check_in'])
                ? $checkInDate->copy()->setTime(...explode(':', $data['planned_check_in']))
                : $checkInDate->copy()->setTime(14, 0);

            $plannedCheckOut = !empty($data['planned_check_out'])
                ? $checkOutDate->copy()->setTime(...explode(':', $data['planned_check_out']))
                : $checkOutDate->copy()->setTime(12, 0);

            /**
             * 4. Buat reservasi
             */
            $reservation = RoomReservation::create([
                'room_type_id'      => $roomTypeId,
                'room_id'           => $room->id,
                'reservation_code'  => $this->generateCode(),

                'check_in_date'     => $data['check_in_date'],
                'check_out_date'    => $data['check_out_date'],
                'nights'            => $calc['nights'],

                'planned_check_in'  => $plannedCheckIn,
                'planned_check_out' => $plannedCheckOut,

                'guest_name'        => $data['guest_name'],
                'guest_phone'       => $data['guest_phone'],
                'guest_email'       => $data['guest_email'] ?? null,

                'total_price'       => $calc['total_price'],
                'payment_status'    => 'pending',
                'reservation_status' => 'booked',

                'payment_due_at'    => $paymentDue
            ]);

            /**
             * 5. Insert nightly prices breakdown
             */
            foreach ($calc['breakdown'] as $item) {
                RoomReservationPrice::create([
                    'reservation_id' => $reservation->id,
                    'date'           => $item['date'],
                    'price'          => $item['price'],
                ]);
            }

            // formatting output
            $reservation->planned_check_in  = Carbon::parse($plannedCheckIn)->format('H.i');
            $reservation->planned_check_out = Carbon::parse($plannedCheckOut)->format('H.i');

            return $reservation;
        });
    }

    public function createReservationUser(array $data)
    {
        return DB::transaction(function () use ($data) {

            /**
             * 1. Jika room_id tidak ada, auto-select berdasarkan room_type_id
             */
            if (empty($data['room_id'])) {
                if (empty($data['room_type_id'])) {
                    abort(422, 'Either room_id or room_type_id is required.');
                }

                $room = $this->findAvailableRoom(
                    $data['room_type_id'],
                    $data['check_in_date'],
                    $data['check_out_date']
                );
            } else {
                // Jika room_id sudah ada, ambil room tersebut
                $room = Room::with('roomType')->findOrFail($data['room_id']);
                
                // CEK KAMAR TERSEDIA
                $this->checkRoomAvailability(
                    $room->id,
                    $data['check_in_date'],
                    $data['check_out_date']
                );
            }

            $roomTypeId = $room->room_type_id;

            /**
             * 2. Hitung harga
             */
            $calc = $this->calculatePrice(
                $roomTypeId,
                $data['check_in_date'],
                $data['check_out_date']
            );

            $paymentDue = Carbon::now()->addMinutes(30);

            /**
             * 3. Gabungkan jam planned check in/out
             */
            $checkInDate  = Carbon::parse($data['check_in_date']);
            $checkOutDate = Carbon::parse($data['check_out_date']);

            $plannedCheckIn = !empty($data['planned_check_in'])
                ? $checkInDate->copy()->setTime(...explode(':', $data['planned_check_in']))
                : $checkInDate->copy()->setTime(14, 0);

            $plannedCheckOut = !empty($data['planned_check_out'])
                ? $checkOutDate->copy()->setTime(...explode(':', $data['planned_check_out']))
                : $checkOutDate->copy()->setTime(12, 0);

            /**
             * Auth check
             */
            $user = Auth::id();

            if (!$user) {
                // kalau user_id ada di request, ambil
                $user = $data['user_id'] ;
            }

            /**
             * 4. Buat reservasi
             */
            $reservation = RoomReservation::create([
                'user_id'           => $user,
                'room_type_id'      => $roomTypeId,
                'room_id'           => $room->id,
                'reservation_code'  => $this->generateCode(),

                'check_in_date'     => $data['check_in_date'],
                'check_out_date'    => $data['check_out_date'],
                'nights'            => $calc['nights'],

                'planned_check_in'  => $plannedCheckIn,
                'planned_check_out' => $plannedCheckOut,

                'guest_name'        => $data['guest_name'],
                'guest_phone'       => $data['guest_phone'],
                'guest_email'       => $data['guest_email'] ?? null,

                'total_price'       => $calc['total_price'],
                'payment_status'    => 'pending',
                'reservation_status' => 'booked',

                'payment_due_at'    => $paymentDue
            ]);

            /**
             * 5. Insert nightly prices breakdown
             */
            foreach ($calc['breakdown'] as $item) {
                RoomReservationPrice::create([
                    'reservation_id' => $reservation->id,
                    'date'           => $item['date'],
                    'price'          => $item['price'],
                ]);
            }

            // formatting output
            $reservation->planned_check_in  = Carbon::parse($plannedCheckIn)->format('H.i');
            $reservation->planned_check_out = Carbon::parse($plannedCheckOut)->format('H.i');

            return $reservation;
        });
    }


    private function generateCode(): string
    {
        return 'RSV-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
    }
}