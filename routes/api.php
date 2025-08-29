<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::get('/user', 'profile')->middleware('auth:sanctum');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');
});
