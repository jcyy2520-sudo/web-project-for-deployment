<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

// Google OAuth routes (uses full web middleware stack with sessions)
Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [GoogleOAuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/callback', [GoogleOAuthController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::post('/verify-email/{verificationCode}', [GoogleOAuthController::class, 'verifyEmail'])->name('google.verify');
});

Route::get('/auth/registration/confirm/{token}', [AuthController::class, 'confirmRegistration'])->name('registration.confirm');
Route::get('/auth/registration/reject/{token}', [AuthController::class, 'rejectRegistration'])->name('registration.reject');

