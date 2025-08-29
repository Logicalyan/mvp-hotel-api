<?php

namespace App\Http\Controllers\Auth;

use App\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponses;

    public function register(Request $request) {

        $validated = $request->validate([
            'name' => 'required|string|max:10',
            'email' => 'required|string|email',
            'password' => 'required|string|min:6'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $role = Role::where('slug', 'customer')->first();
        $role && $user->roles()->attach($role->id);

        //Device
        $deviceName = $request->header('User-Agent') ?? 'unknown device';

        //Buat token
        $token = $user->createToken(
            $deviceName,
            ['*'],
            Carbon::now()->addDays(7)
        )->plainTextToken;

        return $this->success([
            'user' => $user,
            'role' => $user->roles()->pluck('slug')->first(),
            'token' => $token,
            'device' => $deviceName
        ], 'Register Successfully', 201);
    }
}
