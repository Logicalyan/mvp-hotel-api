<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provinces = DB::table('provinces')->pluck('id');

        foreach ($provinces as $provinceId) {
            $path = base_path("database/data/kota/{$provinceId}.json");

            if (!file_exists($path)) {
                continue;
            }

            $json = file_get_contents($path, true);
            $datas = json_decode($json, true);

            foreach ($datas as $data) {
                DB::table('cities')->insert([
                    'id'=> $data['id'],
                    'province_id' => $provinceId,
                    'name' => $data['nama']
                ]);
            }
        }
    }
}
