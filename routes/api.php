<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationExportController;
use App\Http\Controllers\ParserMonitoringController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::post('/organization', [OrganizationController::class, 'store']);
    Route::post('/organization/refresh', [OrganizationController::class, 'refresh']);
    Route::get('/organization/reviews', [ReviewController::class, 'index']);

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'showById']);
    Route::post('/organizations/{organization}/refresh', [OrganizationController::class, 'refreshById']);
    Route::get('/organizations/{organization}/export', OrganizationExportController::class);
    Route::get('/organizations/{organization}/reviews', [ReviewController::class, 'indexForOrganization']);
    Route::get('/organizations/{organization}/rating-history', [OrganizationController::class, 'history']);
    Route::get('/parser-monitoring', [ParserMonitoringController::class, 'summary']);
});
