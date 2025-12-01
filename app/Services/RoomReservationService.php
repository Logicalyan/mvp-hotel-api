<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomPrice;
use App\Models\RoomReservation;
use App\Models\RoomReservationPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RoomReservationService
{
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

    public function createReservation(array $data)
    {
        return DB::transaction(function () use ($data) {

            /**
             * 1. Get room & type
             */
            $room = Room::with('roomType')->findOrFail($data['room_id']);
            $roomTypeId = $room->room_type_id;

            /**
             * 2. Calculate price
             */
            $calc = $this->calculatePrice(
                $roomTypeId,
                $data['check_in_date'],
                $data['check_out_date']
            );

            /**
             * 3. Determine planned check-in/out time
             */

            $checkInDate = Carbon::parse($data['check_in_date']);
            $checkOutDate = Carbon::parse($data['check_out_date']);

            if (!empty($data['planned_check_in'])) {
                // gabungkan tanggal + jam
                [$h, $m] = explode(':', $data['planned_check_in']);
                $plannedCheckIn = $checkInDate->copy()->setTime($h, $m, 0);
            } else {
                $plannedCheckIn = $checkInDate->copy()->setTime(14, 0, 0); // default
            }

            if (!empty($data['planned_check_out'])) {
                [$h, $m] = explode(':', $data['planned_check_out']);
                $plannedCheckOut = $checkOutDate->copy()->setTime($h, $m, 0);
            } else {
                $plannedCheckOut = $checkOutDate->copy()->setTime(12, 0, 0); // default
            }

            /**
             * 4. Create reservation
             */
            $reservation = RoomReservation::create([
                // 'user_id'           => $data['user_id'] ?? null,
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
            ]);

            /**
             * 5. Insert nightly breakdown
             */
            foreach ($calc['breakdown'] as $item) {
                RoomReservationPrice::create([
                    'reservation_id' => $reservation->id,
                    'date'           => $item['date'],
                    'price'          => $item['price'],
                ]);
            }

            $reservation->planned_check_in = Carbon::parse($plannedCheckIn)->format('H.i');
            $reservation->planned_check_out = Carbon::parse($plannedCheckOut)->format('H.i');

            return $reservation;
        });
    }

    private function generateCode(): string
    {
        return 'RSV-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
    }
}
