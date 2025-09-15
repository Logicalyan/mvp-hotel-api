<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
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
            Facility::firstOrCreate(['name' => $facility]);
        }
    }
}
