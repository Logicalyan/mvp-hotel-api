<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Data\Hotel\HotelController;
use App\Http\Controllers\Data\Hotel\Reference\ReferenceController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\Data\BedTypeController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/provinces', [ReferenceController::class, 'provinces']);
Route::get('/cities', [ReferenceController::class, 'cities']);
Route::get('/districts', [ReferenceController::class, 'districts']);
Route::get('/sub-districts', [ReferenceController::class, 'subDistricts']);
Route::get('/facilities', [ReferenceController::class, 'facilities']);
Route::apiResource('hotels', HotelController::class);
Route::apiResource('users', UserController::class);
Route::apiResource('bed-types', BedTypeController::class);
Route::apiResource('room-types', RoomTypeController::class);
Route::apiResource('rooms', RoomController::class);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('reservations', ReservationController::class);
    Route::post('reservations/{id}/pay-remaining', [ReservationController::class, 'payRemaining']);
    Route::middleware(['role:admin'])->group(function () {});
});

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::get('/user', 'profile')->middleware('auth:sanctum');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');
});
