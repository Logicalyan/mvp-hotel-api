<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = file_get_contents(base_path('database/data/provinsi.json'));
        $datas = json_decode($json, true);

        foreach ($datas as $data) {
            DB::table('provinces')->insert([
                'id'=> $data['id'],
                'name' => $data['name']
            ]);
        }
    }
}
