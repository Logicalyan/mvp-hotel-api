<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ambil semua role biar bisa random assign
        $roles = Role::all();

        // bikin 100 dummy user
        for ($i = 1; $i <= 100; $i++) {
            $user = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'), // semua pakai password sama
            ]);

            // kasih role random dari role yang ada
            $role = $roles->random();
            $user->roles()->attach($role->id);
        }
    }
}
