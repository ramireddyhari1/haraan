<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WhatsAppAuthController;
use App\Http\Controllers\Api\BookingsController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| RESTful JSON API for the Haraan platform.
| All routes are automatically prefixed with /api by Laravel.
|
*/

Route::get('/health', static fn () => response()->json([
    'status'    => 'success',
    'message'   => 'Haraan Laravel API is running',
    'timestamp' => now()->toIso8601String(),
]));

// -------------------------------------------------------------------------
//  Authentication
// -------------------------------------------------------------------------

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::middleware('auth.jwt')->get('/me', [AuthController::class, 'me']);
});

Route::prefix('auth/whatsapp')->controller(WhatsAppAuthController::class)->group(function (): void {
    Route::post('/request', 'requestOtp');
    Route::post('/verify', 'verifyOtp');
});

// -------------------------------------------------------------------------
//  Users (admin-only)
// -------------------------------------------------------------------------

Route::middleware('auth.jwt')->prefix('users')->group(function (): void {
    Route::get('/', [UsersController::class, 'index']);
    Route::get('/partners', [UsersController::class, 'partners']);
    Route::post('/partners', [UsersController::class, 'createPartner']);
    Route::get('/{id}', [UsersController::class, 'show']);
    Route::put('/{id}', [UsersController::class, 'update']);
    Route::patch('/{id}/role', [UsersController::class, 'updateRole']);
    Route::patch('/{id}/status', [UsersController::class, 'updateStatus']);
});

// -------------------------------------------------------------------------
//  Events
// -------------------------------------------------------------------------

Route::prefix('events')->group(function (): void {
    Route::get('/', [EventsController::class, 'index']);
    Route::get('/search', [EventsController::class, 'index']);
    Route::get('/categories', [EventsController::class, 'categories']);
    Route::get('/{id}', [EventsController::class, 'show']);

    Route::middleware('auth.jwt')->group(function (): void {
        Route::post('/', [EventsController::class, 'store']);
        Route::put('/{id}', [EventsController::class, 'update']);
    });
});

// -------------------------------------------------------------------------
//  Bookings
// -------------------------------------------------------------------------

Route::middleware('auth.jwt')->prefix('bookings')->group(function (): void {
    Route::get('/', [BookingsController::class, 'index']);
    Route::get('/{id}', [BookingsController::class, 'show']);
    Route::post('/', [BookingsController::class, 'store']);
    Route::patch('/{id}/cancel', [BookingsController::class, 'cancel']);
});
