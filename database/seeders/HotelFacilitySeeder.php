<?php

namespace Database\Seeders;

use App\Models\HotelFacility;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HotelFacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $facilities = [
            'Free Wifi',
            'Swimming Pool',
            'Parking Area',
            'Restaurant',
            'Gym/Fitness Center',
            'Spa & Wellness',
            'Airport Shuttle',
            'Meeting Room',
            '24-Hour Reception',
            'Laundry Service'
        ];

        foreach ($facilities as $facility) {
            HotelFacility::firstOrCreate(['name' => $facility]);
        }
    }
}
