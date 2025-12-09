<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Hotel;
use App\Models\Province;
use App\Models\RoomReservation;

class DashboardController extends Controller
{
    public function index()
    {
        // ===============================
        // 1. User Role Distribution
        // ===============================
        $userRoles = [
            'admin'    => User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->count(),
            'hotel'    => User::whereHas('roles', fn($q) => $q->where('slug', 'hotel'))->count(),
            'customer' => User::whereHas('roles', fn($q) => $q->where('slug', 'customer'))->count(),
        ];

        // ===============================
        // 2. Total Hotels
        // ===============================
        $totalHotels = Hotel::count();

        // ===============================
        // 3. Hotels by Province
        // ===============================
        $hotelsByProvince = Hotel::selectRaw('province_id, COUNT(*) as total')
            ->groupBy('province_id')
            ->with('province:id,name')
            ->get()
            ->map(function ($h) {
                return [
                    'province_id' => $h->province_id,
                    'province'    => $h->province?->name ?? 'Unknown',
                    'total'       => $h->total,
                ];
            });

        // ===============================
        // 4. Reservations Count
        // ===============================
        $totalReservations = RoomReservation::count();

        // ===============================
        // 5. Transaction Count (paid only)
        // ===============================
        $totalTransactions = RoomReservation::where('payment_status', 'paid')->count();

        // ===============================
        // 6. Recent Activities (latest 10)
        // ===============================
        $recentActivities = RoomReservation::latest()
            ->take(10)
            ->get()
            ->map(function ($r) {
                return [
                    'type'    => 'reservation',
                    'message' => "Reservation #{$r->id} created",
                    'date'    => $r->created_at->format('Y-m-d H:i'),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user_roles'         => $userRoles,
                'total_hotels'       => $totalHotels,
                'hotels_by_province' => $hotelsByProvince,
                'total_reservations' => $totalReservations,
                'total_transactions' => $totalTransactions,
                'recent_activities'  => $recentActivities,
            ]
        ]);
    }
}
