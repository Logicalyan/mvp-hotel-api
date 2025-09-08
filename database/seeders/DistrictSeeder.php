<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = DB::table('cities')->pluck('id');

        foreach ($cities as $cityId) {
            $path = base_path("database/data/kecamatan/{$cityId}.json");

            if (!file_exists($path)) {
                continue; // skip kalau file tidak ada
            }

            $json = file_get_contents($path);
            $data = json_decode($json, true);

            foreach ($data as $item) {
                DB::table('districts')->insert([
                    'id'          => $item['id'],
                    'city_id'  => $cityId,
                    'name'        => $item['nama']
                ]);
            }
        }
    }
}
