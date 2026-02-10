<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController as ApiAuthController;
use App\Http\Controllers\Api\V1\Partner\PartnerUserController;
use App\Http\Controllers\Webhooks\TrackingWebhookController;

Route::get('/ping', fn() => response()->json(['ok' => true]));

Route::prefix('v1')->group(function () {
    Route::post('auth/login',  [ApiAuthController::class, 'login']);
    Route::post('auth/logout', [ApiAuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::prefix('partner')->middleware('auth:sanctum')->group(function () {
        Route::get('users', [PartnerUserController::class, 'index']);
        Route::post('users', [PartnerUserController::class, 'store']);
        Route::put('users/{id}', [PartnerUserController::class, 'update']);
        Route::delete('users/{id}', [PartnerUserController::class, 'destroy']);
    });
});


// mise à jour de dashboard en temps reels 
Route::post('/webhooks/tracking', [TrackingWebhookController::class, 'handle'])
  ->name('webhooks.tracking');