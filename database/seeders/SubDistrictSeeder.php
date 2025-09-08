<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubDistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $districts = DB::table('districts')->pluck('id');

        foreach ($districts as $districtId) {
            $path = base_path("database/data/kelurahan/{$districtId}.json");

            if (!file_exists($path)) {
                continue; // skip kalau file tidak ada
            }

            $json = file_get_contents($path);
            $data = json_decode($json, true);

            foreach ($data as $item) {
                DB::table('sub_districts')->insert([
                    'id'           => $item['id'],
                    'district_id'  => $districtId,
                    'name'         => $item['nama']
                ]);
            }
        }
    }
}
