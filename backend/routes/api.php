<?php

use App\Http\Controllers\Api\CryptoController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\PortfolioMovementController;
use App\Http\Controllers\Api\PortfolioShareController;
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
    Route::get('/portfolios/{portfolio}/shares', [PortfolioShareController::class, 'index']);
    Route::post('/portfolios/{portfolio}/shares', [PortfolioShareController::class, 'store']);
    Route::delete('/portfolios/{portfolio}/shares/{share}', [PortfolioShareController::class, 'destroy']);
    Route::get('/portfolio-shares/invitations', [PortfolioShareController::class, 'invitations']);
    Route::post('/portfolio-shares/{share}/accept', [PortfolioShareController::class, 'accept']);
    Route::post('/portfolio-shares/{share}/decline', [PortfolioShareController::class, 'decline']);
});
