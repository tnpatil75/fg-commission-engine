<?php

use App\Http\Controllers\Web\CalculationController;
use App\Http\Controllers\Web\ManualDecisionController;
use App\Http\Controllers\Web\RuleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/calculate');

Route::get('/calculate', [CalculationController::class, 'create'])->name('calculate.create');
Route::post('/calculate', [CalculationController::class, 'store'])->name('calculate.store');

Route::get('/calculations', [CalculationController::class, 'index'])->name('calculations.index');
Route::get('/calculations/{calculation}', [CalculationController::class, 'show'])->name('calculations.show');
Route::post('/calculations/{calculation}/decision', [ManualDecisionController::class, 'store'])->name('calculations.decision.store');

Route::get('/rules', [RuleController::class, 'index'])->name('rules.index');
Route::get('/rules/create', [RuleController::class, 'create'])->name('rules.create');
Route::post('/rules', [RuleController::class, 'store'])->name('rules.store');
Route::get('/rules/{rule}', [RuleController::class, 'show'])->whereNumber('rule')->name('rules.show');
Route::post('/rules/{rule}/versions', [RuleController::class, 'storeVersion'])->whereNumber('rule')->name('rules.versions.store');
Route::post('/rules/{rule}/deactivate', [RuleController::class, 'deactivate'])->whereNumber('rule')->name('rules.deactivate');
