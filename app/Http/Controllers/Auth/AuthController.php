<?php

namespace App\Http\Controllers\Auth;

use App\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponses;

    public function register(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:10',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6'
        ]);

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $role = Role::where('slug', 'customer')->first();
            $role && $user->roles()->attach($role->id);

            $deviceName = $request->header('User-Agent') ?? 'unknown device';

            $token = $user->createToken(
                $deviceName,
                ['*'],
                Carbon::now()->addDays(7)
            )->plainTextToken;

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Registration Failed: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'user' => $user,
            'role' => $user->roles()->pluck('slug')->first(),
            'token' => $token,
            'device' => $deviceName
        ], 'Register Successfully', 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->error('Email or Password Does not match', 401);
        }

        try {
            $deviceName = $request->header('User-Agent') ?? 'unknown device';

            $token = $user->createToken(
                $deviceName,
                ['*'],
                Carbon::now()->addDays(7)
            )->plainTextToken;

            $hotelId = null;
            if ($user->hasRole('hotel')) {
                // Asumsi: User has relationship dengan hotel via staff
                $hotelId = $user->hotelStaff()->first()?->hotel_id;

                // Atau kalau structure beda:
                // $hotelId = $user->hotels()->first()?->id;
            }

            // ✅ Get role slug
            $roleSlug = $user->roles()->pluck('slug')->first();

            return $this->success([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $roleSlug,  // ← Pastikan ini string
                    'hotel_id' => $hotelId,
                ],
                'token' => $token,
                'device' => $deviceName,
                'hotel_id' => $hotelId,
            ], 'Login Successfully');
        } catch (\Exception $e) {
            return $this->error('Login Failed: ' . $e->getMessage(), 500);
        }
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $roleSlug = $user->roles()->pluck('slug')->first();
        $hotelId = null;

        if ($user->hasRole('hotel')) {
            $hotelId = $user->hotelStaff()->first()?->hotel_id;
        }

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $roleSlug,
            'hotel_id' => $hotelId,
        ], 'Get Profile Successfully');
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return $this->success([], 'Logout Successfully');
    }
}
