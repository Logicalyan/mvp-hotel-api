<?php

namespace Database\Seeders;

use App\Models\RoomTypeFacility;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoomFacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $facilities = [
            'TV',
            'Air Conditioner',
            'Warm Shower',
            'Wardrobe',
        ];

        foreach ($facilities as $facility) {
            RoomTypeFacility::firstOrCreate(['name' => $facility]);
        }
    }
}
