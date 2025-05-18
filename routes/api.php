<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiServiceController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\AuthController;



//auth
// Registration toggle status (for frontend)
Route::get('/registration-status', function () {
    return response()->json([
        'registration_enabled' => config('auth.registration_enabled'),
    ]);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::middleware(['auth'])->group(function () {
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

// AUTHENTICATED USER DATA
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API SERVICE ROUTES (Secured with `auth:sanctum` & Rate Limiting)
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/tel/{number}', [ApiServiceController::class, 'getTelData']);
    Route::get('/email/{email}', [ApiServiceController::class, 'getEmailData']);
});

// googlelogin route
Route::post('/google-login', [GoogleAuthController::class, 'googleLogin']);


