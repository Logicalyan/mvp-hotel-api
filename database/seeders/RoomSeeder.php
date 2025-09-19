<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $hotels = Hotel::with('roomTypes')->get();

        foreach ($hotels as $hotel) {
            $hotelPrefix = strtoupper(substr(str_replace(' ', '', $hotel->name), 0, 3));
            // Ambil 3 huruf depan hotel tanpa spasi

            foreach ($hotel->roomTypes as $roomType) {
                // Misal generate 5 room per tipe
                for ($i = 1; $i <= 5; $i++) {
                    $room = Room::create([
                        'room_type_id' => $roomType->id,
                        'floor' => rand(1, 5),
                        'status' => 'available',
                        'is_active' => true,
                        'room_number' => '' // sementara kosong
                    ]);

                    // Update room_number setelah dapat ID
                    $room->update([
                        'room_number' => $hotelPrefix . '-' . strtoupper($roomType->name) . '-' . $room->id
                    ]);
                }
            }
        }
    }
}
