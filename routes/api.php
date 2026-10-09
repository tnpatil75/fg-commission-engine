<?php

use App\Http\Controllers\Api\CalculationController;
use App\Http\Controllers\Api\MasterDataController;
use Illuminate\Support\Facades\Route;

// Unauthenticated LOCAL DEMO API. Authentication and authorised access are required before any deployment.

Route::get('/masters', MasterDataController::class)->name('api.masters');
Route::post('/calculations', [CalculationController::class, 'store'])->name('api.calculations.store');
Route::get('/calculations/{calculation}', [CalculationController::class, 'show'])->name('api.calculations.show');
