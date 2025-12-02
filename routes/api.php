<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Data\Hotel\HotelController;
use App\Http\Controllers\Data\Hotel\Reference\ReferenceController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\Data\BedTypeController;
use App\Http\Controllers\Data\Hotel\Facility\FacilityController;
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

Route::get('hotel/{hotel_id}/reservations/{reservation_id}', [RoomReservationController::class, 'showByHotelId'])->name('reservation.detail');


// routes/web.php atau api.php
// Route::post('/midtrans/webhook', function (Request $request) {
//     $serverKey = config('services.midtrans.server_key');
//     $hashed = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . $serverKey);

//     if ($hashed !== $request->signature_key) {
//         return response('Invalid signature', 403);
//     }

//     $orderId = $request->order_id;
//     $status = $request->transaction_status;

//     $reservation = RoomReservation::where('reservation_code', $orderId)->first();

//     if (!$reservation) return response('Reservation not found', 404);

//     if ($status == 'capture' || $status == 'settlement') {
//         $reservation->update(['payment_status' => 'paid']);
//     } elseif ($status == 'deny' || $status == 'cancel' || $status == 'expire') {
//         $reservation->update(['payment_status' => 'pending']);
//     }

//     return response('OK', 200);
// });

Route::patch('rooms/{id}/toggle-status', [RoomController::class, 'toggleStatus']);
Route::apiResource('hotel-facilities', FacilityController::class);
Route::apiResource('room-facilities', RoomFacilityController::class);
Route::apiResource('hotels', HotelController::class);
Route::apiResource('bed-types', BedTypeController::class);
Route::apiResource('room-types', RoomTypeController::class);
Route::apiResource('rooms', RoomController::class);

//Reservations Status
Route::post('/reservations/{id}/cancel', [RoomReservationController::class, 'cancel']);
Route::post('/reservations/{id}/check-in', [CheckInController::class, 'checkIn']);
Route::post('/reservations/{id}/check-out', [CheckoutController::class, 'checkout']);

Route::post('/midtrans/callback', [ReservationPaymentController::class, 'midtransCallback'])->name('midtrans.callback');

Route::middleware(['auth:sanctum'])->group(function () {

    // Route::apiResource('reservations', ReservationController::class);
    // Route::post('reservations/{id}/pay-remaining', [ReservationController::class, 'payRemaining']);
    // Route::middleware(['role:admin'])->group(function () {});
});

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::get('/user', 'profile')->middleware('auth:sanctum');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');
});
