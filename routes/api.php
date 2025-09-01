<?php

use App\Http\Controllers\Auth\AuthController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::get('/user', 'profile')->middleware('auth:sanctum');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');
});

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $user = User::findOrFail($request->route('id'));

    // cek sudah diverifikasi
    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email already verified']);
    }

    // tandai verified
    if ($user->markEmailAsVerified()) {
        event(new Verified($user));
    }

    return response()->json(['message' => 'Email verified successfully']);
})->name('verification.verify')->middleware('auth:sanctum');
