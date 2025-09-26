<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/dashboard', [\App\Http\Controllers\Dashboard\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Dashboard routes
    Route::get('/dashboard/chart/data', [\App\Http\Controllers\Dashboard\DashboardController::class, 'chartData'])->name('dashboard.chart.data');
    Route::get('/dashboard/stats', [\App\Http\Controllers\Dashboard\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/dashboard/spending-by-category', [\App\Http\Controllers\Dashboard\DashboardController::class, 'spendingByCategory'])->name('dashboard.spending.by.category');

    // Receipt management routes
    Route::resource('receipts', \App\Http\Controllers\ReceiptController::class);
    Route::get('/categories', [\App\Http\Controllers\ReceiptController::class, 'categories'])->name('categories');
    Route::post('/receipts/{receipt}/retry', [\App\Http\Controllers\ReceiptController::class, 'retry'])->name('receipts.retry');
    Route::get('/receipts/{receipt}/file', [\App\Http\Controllers\ReceiptController::class, 'file'])->name('receipts.file');
});

require __DIR__.'/auth.php';
