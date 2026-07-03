<?php

use Illuminate\Support\Facades\Route;
use Jaydeep\LaravelTimeMachine\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('time-machine.index');
Route::delete('/', [DashboardController::class, 'clear'])->name('time-machine.clear');
Route::get('/{id}', [DashboardController::class, 'show'])->name('time-machine.show');
