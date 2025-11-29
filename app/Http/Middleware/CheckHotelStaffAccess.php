<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckHotelStaffAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $position
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ?string $position = null)
    {
        $user = $request->user();

        // Pastikan user login
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Ambil hotel_id dari route
        $hotelId = $request->route('hotel_id');
        if (!$hotelId || !is_numeric($hotelId)) {
            return response()->json(['error' => 'Hotel ID not specified or invalid'], 400);
        }
        $hotelId = (int) $hotelId;

        // Super admin bypass
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Cek apakah user staff di hotel ini
        if (!$user->isStaffOfHotel($hotelId)) {
            return response()->json([
                'error' => 'You are not authorized to access this hotel'
            ], 403);
        }

        // Jika perlu position spesifik
        if ($position && !$user->hasPositionInHotel($position, $hotelId)) {
            return response()->json([
                'error' => 'Insufficient permissions for this action'
            ], 403);
        }

        return $next($request);
    }
}
