<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController as ApiAuthController;
use App\Http\Controllers\Api\V1\Partner\PartnerUserController;
use App\Http\Controllers\Webhooks\TrackingWebhookController;

Route::prefix('v1')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Auth API
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('login', [ApiAuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [ApiAuthController::class, 'logout']);
            Route::post('logout-all', [ApiAuthController::class, 'logoutAll']);
            Route::get('sessions', [ApiAuthController::class, 'sessions']);
            Route::delete('sessions/{tokenId}', [ApiAuthController::class, 'revokeToken']);
            Route::get('me', [ApiAuthController::class, 'me']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Partner API
    |--------------------------------------------------------------------------
    */
    Route::prefix('partner')->middleware('auth:sanctum')->group(function () {
        Route::get('users', [PartnerUserController::class, 'index']);
        Route::post('users', [PartnerUserController::class, 'store']);
        Route::put('users/{id}', [PartnerUserController::class, 'update']);
        Route::delete('users/{id}', [PartnerUserController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
*/
Route::post('webhooks/tracking', [TrackingWebhookController::class, 'handle'])
    ->name('webhooks.tracking');