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
// use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomReservationController;
use App\Http\Controllers\UserController;
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

Route::middleware(['auth:sanctum','check.hotel.staff', 'role:hotel'])->group(function () {
    Route::get('hotel/{hotel_id}/dashboard', [HotelController::class, 'dashboard']);
    Route::get('hotel/{hotel_id}/room-types', [RoomTypeController::class, 'indexByHotelId']);
    Route::get('hotel/{hotel_id}/room-types/{room_type_id}', [RoomTypeController::class, 'showByHotelId']);
    Route::post('hotel/{hotel_id}/room-types', [RoomTypeController::class, 'storeByHotelId']);
    Route::delete('hotel/{hotel_id}/room-types/{room_type_id}', [RoomTypeController::class, 'destroyByHotelId']);
});

Route::apiResource('hotel-facilities', FacilityController::class);
Route::apiResource('room-facilities', RoomFacilityController::class);
Route::apiResource('hotels', HotelController::class);
Route::apiResource('users', UserController::class);
Route::apiResource('bed-types', BedTypeController::class);
Route::apiResource('room-types', RoomTypeController::class);
Route::apiResource('rooms', RoomController::class);

// api.php atau web.php
Route::post('/reservations/calculate-price', [RoomReservationController::class, 'calculatePrice']);
Route::apiResource('reservations', RoomReservationController::class);
Route::patch('/reservations/{id}/status', [RoomReservationController::class, 'updateStatus']);
Route::patch('/reservations/{id}/payment', [RoomReservationController::class, 'updatePaymentStatus']);
Route::post('/reservations/{id}/cancel', [RoomReservationController::class, 'cancel']);
Route::post('/reservations/{id}/checkout', [CheckoutController::class, 'checkout']);
Route::post('/reservations/{id}/check-in', [CheckInController::class, 'checkIn']);

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
