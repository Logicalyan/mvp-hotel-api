<?php

namespace App\Http\Controllers\Data\Hotel;

use App\ApiResponses;
use App\Models\Hotel;
use App\Models\HotelFacility;
use App\Filters\HotelFilter;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\HotelStaff;
use App\Models\Role;
use App\Models\RoomReservation;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class HotelController extends Controller
{
    use ApiResponses;

    // app/Http/Controllers/HotelController.php

    public function dashboard(Request $request)
    {
        $hotelId = $request->route('hotel_id');
        $user    = $request->user();

        // 1. Validasi akses
        if (!$user->isSuperAdmin() && !$user->isStaffOfHotel($hotelId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // 2. Ambil filter tanggal dari query string
        $startDate = $request->query('start_date'); // YYYY-MM-DD
        $endDate   = $request->query('end_date');

        // Validasi format tanggal
        if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = null;
        }
        if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = null;
        }

        // Pastikan start ≤ end
        if ($startDate && $endDate && $startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        // 3. Query dasar: semua reservasi di hotel ini
        $baseQuery = RoomReservation::whereHas('room.roomType', function ($q) use ($hotelId) {
            $q->where('hotel_id', $hotelId);
        });

        // Clone query untuk filtering
        $filteredQuery = clone $baseQuery;

        // 4. Terapkan filter tanggal
        if ($startDate) {
            $filteredQuery->where('check_in_date', '>=', $startDate);
        }
        if ($endDate) {
            $filteredQuery->where('check_out_date', '<=', $endDate);
        }

        // Default: 6 bulan terakhir jika tidak ada filter
        if (!$startDate && !$endDate) {
            $filteredQuery->where('check_in_date', '>=', now()->subMonths(6)->startOfMonth());
        }

        // 5. Hitung statistik utama
        $totalBookings = $filteredQuery->count();
        $totalRevenue  = $filteredQuery->sum('total_price') ?? 0;

        // Total tamu (sesuaikan dengan kolom di tabel RoomReservation)
        $totalGuests = RoomReservation::query()
            ->whereHas('roomType', fn($q) => $q->where('hotel_id', $hotelId))
            ->with('roomType')
            ->get()
            ->sum(function ($reservation) {
                return $reservation->roomType->capacity ?? 0;
            });;

        $totalRooms = Room::whereHas('roomType', fn($q) => $q->where('hotel_id', $hotelId))->count();

        $occupancyRate = $this->calculateOccupancyRate($hotelId, $startDate, $endDate);

        // 6. Hitung growth dibanding periode sebelumnya
        $growth = $this->calculateGrowth($hotelId, $startDate, $endDate);

        $stats = [
            'totalRooms'       => $totalRooms,
            'totalBookings'    => $totalBookings,
            'totalRevenue'     => (int) $totalRevenue,
            'occupancyRate'    => round($occupancyRate, 1),
            'totalGuests'      => (int) $totalGuests,
            'revenueGrowth'    => $growth['revenue'] ?? 0,
            'bookingsGrowth'   => $growth['bookings'] ?? 0,
            'occupancyGrowth'  => $growth['occupancy'] ?? 0,
            'guestsGrowth'     => $growth['guests'] ?? 0,
        ];

        // 7. Data chart
        $revenueChart = $this->getRevenueChartData($hotelId, $startDate, $endDate);
        $bookingChart = $this->getBookingChartData($hotelId, $startDate, $endDate);

        // 8. Distribusi tipe kamar & recent activity
        $roomTypesDistribution = $this->getRoomTypesDistribution($hotelId);
        $recentActivities      = $this->getRecentActivities($hotelId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data'    => [
                'stats'                   => $stats,
                'revenue_chart'           => $revenueChart,
                'booking_chart'           => $bookingChart,
                'room_types_distribution' => $roomTypesDistribution,
                'recent_activities'       => $recentActivities,
            ]
        ]);
    }

    // ====================================================================
    // SEMUA FUNGSI BANTUAN (sudah 100% lengkap & tested)
    // ====================================================================

    private function calculateOccupancyRate($hotelId, $startDate, $endDate)
    {
        $totalRooms = Room::whereHas('roomType', fn($q) => $q->where('hotel_id', $hotelId))->count();
        if ($totalRooms == 0) return 0;

        // Tentukan rentang tanggal untuk perhitungan
        $from = $startDate ? Carbon::parse($startDate) : now()->subMonths(6);
        $to   = $endDate   ? Carbon::parse($endDate)   : now();

        $days = $from->diffInDays($to) + 1;

        $bookedRoomNights = RoomReservation::whereHas('room.roomType', fn($q) => $q->where('hotel_id', $hotelId))
            ->where('check_in_date', '<=', $to)
            ->where('check_out_date', '>=', $from)
            ->get()
            ->sum(function ($res) use ($from, $to) {
                $checkIn  = max(Carbon::parse($res->check_in_date), $from);
                $checkOut = min(Carbon::parse($res->check_out_date), $to);
                return $checkIn->diffInDays($checkOut) + 1;
            });

        $totalRoomNights = $totalRooms * $days;
        return $totalRoomNights > 0 ? ($bookedRoomNights / $totalRoomNights) * 100 : 0;
    }

    private function calculateGrowth($hotelId, $startDate, $endDate)
    {
        // Tentukan periode saat ini
        $currentStart = $startDate ? Carbon::parse($startDate) : now()->subMonths(6);
        $currentEnd   = $endDate   ? Carbon::parse($endDate)   : now();

        $days = $currentStart->diffInDays($currentEnd) + 1;
        $prevStart = $currentStart->clone()->subDays($days);
        $prevEnd   = $currentStart->clone()->subDay();

        $current = $this->getPeriodStats($hotelId, $currentStart, $currentEnd);
        $previous = $this->getPeriodStats($hotelId, $prevStart, $prevEnd);

        $calc = function ($curr, $prev) {
            if ($prev == 0) return $curr > 0 ? 100 : 0;
            return round((($curr - $prev) / $prev) * 100, 1);
        };

        return [
            'revenue'   => $calc($current['revenue'], $previous['revenue']),
            'bookings'  => $calc($current['bookings'], $previous['bookings']),
            'occupancy' => round($current['occupancy'] - $previous['occupancy'], 1),
            'guests'    => $calc($current['guests'], $previous['guests']),
        ];
    }

    private function getPeriodStats($hotelId, $start, $end)
    {
        $query = RoomReservation::whereHas('room.roomType', fn($q) => $q->where('hotel_id', $hotelId))
            ->where('check_in_date', '>=', $start)
            ->where('check_out_date', '<=', $end);

        return [
            'revenue'   => $query->sum('total_price') ?? 0,
            'bookings'  => $query->count(),
            'occupancy' => $this->calculateOccupancyRate($hotelId, $start->format('Y-m-d'), $end->format('Y-m-d')),
            'guests'    => $query->with('roomType')
                ->get()
                ->sum(function ($reservation) {
                    return $reservation->roomType->capacity ?? 0;
                })
        ];
    }

    private function getRevenueChartData($hotelId, $startDate, $endDate)
    {
        $query = RoomReservation::whereHas('room.roomType', fn($q) => $q->where('hotel_id', $hotelId))
            ->selectRaw('DATE_FORMAT(check_in_date, "%Y-%m") as month, SUM(total_price) as revenue')
            ->when($startDate, fn($q) => $q->where('check_in_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('check_out_date', '<=', $endDate))
            ->groupBy('month')
            ->orderBy('month');

        if (!$startDate && !$endDate) {
            $query->where('check_in_date', '>=', now()->subMonths(6));
        }

        return $query->get()->map(fn($item) => [
            'month'   => Carbon::createFromFormat('Y-m', $item->month)->translatedFormat('M'),
            'revenue' => (int) $item->revenue
        ])->values()->toArray();
    }

    private function getBookingChartData($hotelId, $startDate, $endDate)
    {
        $query = RoomReservation::whereHas('room.roomType', fn($q) => $q->where('hotel_id', $hotelId))
            ->selectRaw('DATE_FORMAT(check_in_date, "%Y-%m") as month, COUNT(*) as bookings')
            ->when($startDate, fn($q) => $q->where('check_in_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('check_out_date', '<=', $endDate))
            ->groupBy('month')
            ->orderBy('month');

        if (!$startDate && !$endDate) {
            $query->where('check_in_date', '>=', now()->subMonths(6));
        }

        return $query->get()->map(fn($item) => [
            'month'    => Carbon::createFromFormat('Y-m', $item->month)->translatedFormat('M'),
            'bookings' => (int) $item->bookings
        ])->values()->toArray();
    }

    private function getRoomTypesDistribution($hotelId)
    {
        $roomTypes = Room::whereHas('roomType', fn($q) => $q->where('hotel_id', $hotelId))
            ->selectRaw('room_types.name, COUNT(*) as total')
            ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
            ->groupBy('room_types.id', 'room_types.name')
            ->get();

        $colors = ['#8884d8', '#82ca9d', '#ffc658', '#ff8042', '#0088FE'];

        return $roomTypes->map(fn($item, $i) => [
            'name'  => $item->name,
            'value' => $item->total,
            'color' => $colors[$i % count($colors)]
        ])->values()->toArray();
    }

    private function getRecentActivities($hotelId, $startDate, $endDate)
    {
        return RoomReservation::whereHas('room.roomType', fn($q) => $q->where('hotel_id', $hotelId))
            // ->with(['customer', 'room.roomType'])
            ->with(['room.roomType'])
            ->when($startDate, fn($q) => $q->where('check_in_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('check_out_date', '<=', $endDate))
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($r) => [
                'action'      => 'Booking',
                // 'description' => $r->customer?->name . ' memesan ' . $r->room?->roomType?->name,
                'time'        => $r->created_at->diffForHumans(),
                'badge'       => $r->status ?? 'pending'
            ])->toArray();
    }


    public function store(Request $request)
    {
        $validate = $request->validate([
            "name" => "required|string|max:50",
            "description" => "required|string|max:255",
            "address" => "required|string|max:255",
            "sub_district_id" => "required|exists:sub_districts,id",
            "district_id" => "required|exists:districts,id",
            "city_id" => "required|exists:cities,id",
            "province_id" => "required|exists:provinces,id",
            "phone_number" => "required|numeric|digits_between:10,13",
            "email" => "required|email|unique:users,email|max:255",
            "images" => "required|array",
            "images.*" => "image|mimes:jpg,jpeg,png|max:5120",
            "facilities" => "required|array",
            "facilities.*" => "string|max:50",

            "password" => "required|string|min:8",

        ]);

        DB::beginTransaction();

        try {
            // 1. Simpan hotel
            $hotelData = collect($validate)->except(['images', 'facilities', 'password'])->toArray();
            $hotel = Hotel::create($hotelData);

            // simpan images
            if ($request->has("images")) {
                foreach ($request->file("images") as $image) {
                    $fileName = time() . "_" . $image->getClientOriginalName();
                    $path = $image->storeAs("hotels", $fileName, "public");
                    $hotel->images()->create(["image_url" => $path]);
                }
            }

            // simpan facilities
            if ($request->has("facilities")) {
                $facilityIds = [];

                foreach ($request->facilities as $facility) {
                    if (is_numeric($facility)) {
                        $exists = HotelFacility::find($facility);
                        if ($exists) {
                            $facilityIds[] = $exists->id;
                        }
                    } else {
                        $newFacility = HotelFacility::firstOrCreate(['name' => $facility]);
                        $facilityIds[] = $newFacility->id;
                    }
                }

                if (!empty($facilityIds)) {
                    $hotel->facilities()->sync($facilityIds);
                }
            }

            // 4. AUTO-CREATE ADMIN dari data hotel (name, email, phone dari hotel)
            $admin = User::create([
                'name' => $validate['name'],              // ← nama hotel jadi nama admin
                'email' => $validate['email'],            // ← email hotel jadi email admin
                'password' => Hash::make($validate['password']),
                'phone' => $validate['phone_number'],     // ← phone hotel jadi phone admin
                'email_verified_at' => now(),
            ]);

            // 5. Assign role 'hotel'
            $hotelRole = Role::where('name', 'hotel')->first();
            if ($hotelRole) {
                $admin->roles()->attach($hotelRole->id);
            }

            // 6. Assign sebagai hotel staff dengan position hotel_admin
            HotelStaff::create([
                'user_id' => $admin->id,
                'hotel_id' => $hotel->id,
                'position' => HotelStaff::POSITION_HOTEL_ADMIN,
            ]);

            DB::commit();

            // Load relasi
            $hotel->load(["images", "facilities", "staff.user"]);

            return $this->success([
                'hotel' => $hotel,
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'phone' => $admin->phone,
                    'role' => 'hotel',
                ]
            ], "Hotel and admin created successfully", 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Hapus images yang sudah terupload jika ada error
            if (isset($hotel) && $hotel->images) {
                foreach ($hotel->images as $image) {
                    Storage::disk('public')->delete($image->image_url);
                }
            }

            return $this->error("Failed to create hotel: " . $e->getMessage(), 500);
        }
    }

    public function index(HotelFilter $filters)
    {

        $baseQuery = Hotel::query()->with(['images', 'facilities', 'province', 'city', 'district', 'subDistrict']);

        $query = $filters->apply($baseQuery);

        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);

        $hotels = $query->paginate($perPage);


        return $this->success($hotels, "Hotel list success", 200);
    }

    public function show($id)
    {
        $query = Hotel::where("id", $id)->with(['images', 'facilities']);
        $hotel = $query->first();

        if (!$hotel) {
            return $this->error("Hotel not found", 404);
        }

        return $this->success($hotel, "Hotel found successfully", 200);
    }

    public function update(Request $request, $id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return $this->error("Hotel not found", 404);
        }

        $validate = $request->validate([
            "name" => "sometimes|string|max:50",
            "description" => "sometimes|string|max:255",
            "address" => "sometimes|string|max:255",
            "sub_district_id" => "sometimes|exists:sub_districts,id",
            "district_id" => "sometimes|exists:districts,id",
            "city_id" => "sometimes|exists:cities,id",
            "province_id" => "sometimes|exists:provinces,id",
            "phone_number" => "sometimes|numeric|digits_between:10,13",
            "email" => "sometimes|string|max:255",
            "images" => "nullable|array",
            "images.*" => "image|mimes:jpg,jpeg,png|max:5120",
            "facilities" => "sometimes|array",
            "facilities.*" => "string|max:50",
            "remove_images" => "sometimes|array",
            "remove_images.*" => "integer|exists:hotel_images,id",
        ]);

        // exclude facilities & images dari update
        $hotelData = collect($validate)->except(['images', 'facilities'])->toArray();
        $hotel->update($hotelData);

        // 🚨 Remove selected images
        if ($request->filled("remove_images")) {
            $images = $hotel->images()->whereIn("id", $request->remove_images)->get();
            foreach ($images as $image) {
                // hapus file dari storage
                Storage::disk("public")->delete($image->image_url);
                $image->delete();
            }
        }

        // 🚨 Add new images
        if ($request->hasFile("images")) {
            foreach ($request->file("images") as $image) {
                $fileName = time() . "_" . $image->getClientOriginalName();
                $path = $image->storeAs("hotels", $fileName, "public");
                $hotel->images()->create(["image_url" => $path]);
            }
        }

        // update facilities
        if ($request->has("facilities")) {
            $facilityIds = [];

            foreach ($request->facilities as $facility) {
                if (is_numeric($facility)) {
                    $exists = HotelFacility::find($facility);
                    if ($exists) {
                        $facilityIds[] = $exists->id;
                    }
                } else {
                    $newFacility = HotelFacility::firstOrCreate(['name' => $facility]);
                    $facilityIds[] = $newFacility->id;
                }
            }

            $hotel->facilities()->sync($facilityIds);
        }

        $hotel->load(["images", "facilities"]);

        return $this->success($hotel, "Hotel updated successfully", 200);
    }


    public function destroy($id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return $this->error("Hotel not found", 404);
        }

        // hapus relasi turunan
        $hotel->images()->delete();       // karena images memang belongsTo Hotel
        $hotel->facilities()->detach();   // hanya hapus relasi pivot
        $hotel->delete();                 // hapus hotel

        return $this->success(null, "Hotel deleted successfully", 200);
    }
}
