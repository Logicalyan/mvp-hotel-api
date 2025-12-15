<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Data\Hotel\HotelController;
use App\Http\Controllers\Data\Hotel\Reference\ReferenceController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\Data\BedTypeController;
use App\Http\Controllers\Data\Hotel\Facility\FacilityController;
use App\Http\Controllers\Data\Hotel\Facility\HotelFacilityController;
use App\Http\Controllers\Data\Room\Facility\RoomFacilityController;
use App\Http\Controllers\MidtransController;
use App\Http\Controllers\ReservationPaymentController;
// use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomReservationController;
use App\Http\Controllers\UserController;
use App\Models\RoomReservation;
use Illuminate\Http\Request;
// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// routes/api.php - TARUH DI PALING ATAS, SEBELUM SEMUA ROUTE LAIN

Route::get('/reservations/{id}', function($id) {
    try {
        $reservation = \App\Models\RoomReservation::with([
            'roomType' => function($q) {
                $q->with(['hotel', 'facilities', 'images', 'prices']);
            },
            'room',
            'user'
        ])->find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Reservation not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $reservation
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::prefix('/references')->group(function () {
    Route::get('/provinces', [ReferenceController::class, 'provinces']);
    Route::get('/cities', [ReferenceController::class, 'cities']);
    Route::get('/districts', [ReferenceController::class, 'districts']);
    Route::get('/sub-districts', [ReferenceController::class, 'subDistricts']);
    Route::get('/facilities', [ReferenceController::class, 'facilities']);
    Route::get('/roles', [RoleController::class, 'index']);

    Route::get('/hotels', [ReferenceController::class, 'hotels']);
    Route::get('/room-type-facilities', [ReferenceController::class, 'roomTypeFacilities']);
    Route::get('/bed-types', [ReferenceController::class, 'bedTypes']);
});

Route::middleware(['auth:sanctum', 'check.hotel.staff', 'role:hotel'])->group(function () {
    //Dashboard
    Route::get('hotel/{hotel_id}/dashboard', [HotelController::class, 'dashboard']);
    // Route::get('hotel/{hotel_id}', [HotelController::class, 'show']);
    Route::put('hotel/{hotel_id}/edit', [HotelController::class, 'update']);
    // Route::apiResource('hotel-facilities', FacilityController::class);

    //facilities
    Route::get('hotel/{hotel_id}/facilities', [HotelFacilityController::class, 'indexByHotelId']);
    Route::post('hotel/{hotel_id}/facilities', [HotelFacilityController::class, 'storeForHotel']);
    Route::delete('hotel/{hotel_id}/facilities/{facility_id}', [HotelFacilityController::class, 'detachFromHotel']);

    //Room Type Management
    Route::get('hotel/{hotel_id}/room-types', [RoomTypeController::class, 'indexByHotelId']);
    Route::post('hotel/{hotel_id}/room-types', [RoomTypeController::class, 'storeByHotelId']);
    Route::get('hotel/{hotel_id}/room-types/{room_type_id}', [RoomTypeController::class, 'showByHotelId']);
    Route::put('hotel/{hotel_id}/room-types/{room_type_id}', [RoomTypeController::class, 'updateByHotelId']);
    Route::delete('hotel/{hotel_id}/room-types/{room_type_id}', [RoomTypeController::class, 'destroyByHotelId']);

    //Rooms Management
    Route::get('hotel/{hotel_id}/rooms', [RoomController::class, 'indexByHotelId']);
    Route::get('hotel/{hotel_id}/room-type/{room_type_id}/rooms', [RoomController::class, 'indexByRoomTypeId']);
    Route::post('hotel/{hotel_id}/rooms', [RoomController::class, 'store']);
    Route::get('hotel/{hotel_id}/room-type/{room_type_id}/rooms/{room_id}', [RoomController::class, 'showByRoomTypeId']);
    // Route::post('hotel/{hotel_id}/room-types', [RoomTypeController::class, 'storeByHotelId']);
    // Route::put('hotel/{hotel_id}/room-types/{room_type_id}', [RoomTypeController::class, 'updateByHotelId']);
    // Route::delete('hotel/{hotel_id}/room-types/{room_type_id}', [RoomTypeController::class, 'destroyByHotelId']);

    //Reservations Management
    Route::get('hotel/{hotel_id}/reservations', [RoomReservationController::class, 'indexByHotelId']);
    Route::get('hotel/{hotel_id}/reservations/{reservation_id}', [RoomReservationController::class, 'showByHotelId'])->name('reservation.detail');
    Route::post('hotel/{hotel_id}/reservations', [RoomReservationController::class, 'storeByHotelId']);

    //Midtrans Payment
    Route::post('hotel/{hotel_id}/reservations/{reservation_id}/midtrans/token', [MidtransController::class, 'createSnapToken']);
});

Route::get('hotel/{hotel_id}/reservations/{reservation_id}', [RoomReservationController::class, 'showByHotelId']);

Route::patch('rooms/{id}/toggle-status', [RoomController::class, 'toggleStatus']);
Route::apiResource('hotel-facilities', HotelFacilityController::class);
Route::apiResource('room-facilities', RoomFacilityController::class);
Route::apiResource('hotels', HotelController::class);
Route::apiResource('bed-types', BedTypeController::class);
Route::apiResource('room-types', RoomTypeController::class);
Route::apiResource('rooms', RoomController::class);
Route::apiResource('users', UserController::class);
Route::get('hostel/{hotel_id}/room-types/{room_type_id}', [RoomTypeController::class, 'showByHotelId']);
    Route::get('hostel/{hotel_id}/room-types', [RoomTypeController::class, 'indexByHotelId']);


//Reservations Status
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/reservations', [RoomReservationController::class, 'index']);
Route::post('/reservations/{id}/cancel', [RoomReservationController::class, 'cancel']);
Route::post('/reservations/{id}/expire', [RoomReservationController::class, 'expire']);
Route::post('/reservations/{id}/check-in', [CheckInController::class, 'checkIn']);
Route::post('/reservations/{id}/check-out', [CheckoutController::class, 'checkOut']);

Route::post('/midtrans/callback', [ReservationPaymentController::class, 'midtransCallback'])->name('midtrans.callback');

Route::middleware(['auth:sanctum'])->group(function () {
    // Route::apiResource('reservations', ReservationController::class);
    // Route::post('reservations/{id}/pay-remaining', [ReservationController::class, 'payRemaining']);
    // Route::middleware(['role:admin'])->group(function () {});
    Route::post('/user/reservations/{id}/midtrans/token', [MidtransController::class, 'createUserSnapToken']);
    Route::get('/user/reservations', [RoomReservationController::class, 'reservationByUserId']);
    Route::post('/reservations/user', [RoomReservationController::class, 'store']);

});

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::get('/user', 'profile')->middleware('auth:sanctum');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');

    Route::post('/reset-password-request', 'resetPasswordRequest');
    Route::post('/verify-otp', 'verifyOTP');
    Route::post('/reset-password', 'resetPassword');
});

// Route::get('/reservations/{id}', [RoomReservationController::class, 'show']);
