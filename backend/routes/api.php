<?php

use App\Http\Controllers\Api\CryptoController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\PortfolioMovementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware('auth.keycloak')->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::patch('/user', [UserController::class, 'update']);
    Route::get('/crypto/coins', [CryptoController::class, 'coins']);
    Route::get('/crypto/prices', [CryptoController::class, 'prices']);
    Route::get('/portfolios', [PortfolioController::class, 'index']);
    Route::post('/portfolios', [PortfolioController::class, 'store']);
    Route::get('/portfolios/{portfolio}', [PortfolioController::class, 'show']);
    Route::patch('/portfolios/{portfolio}', [PortfolioController::class, 'update']);
    Route::post('/portfolios/{portfolio}/sync', [PortfolioController::class, 'sync']);
    Route::delete('/portfolios/{portfolio}', [PortfolioController::class, 'destroy']);
    Route::get('/portfolios/{portfolio}/movements', [PortfolioMovementController::class, 'index']);
});
