<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/dashboard/categories', [\App\Http\Controllers\Dashboard\DashboardController::class, 'categories'])->name('dashboard.categories');
    Route::get('/dashboard/stats', [\App\Http\Controllers\Dashboard\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/dashboard/spending-by-category', [\App\Http\Controllers\Dashboard\DashboardController::class, 'spendingByCategory'])->name('dashboard.spending.by.category');
    Route::get('/dashboard/overview', [\App\Http\Controllers\Dashboard\DashboardController::class, 'overview'])->name('dashboard.overview');
    Route::get('/dashboard/trend', [\App\Http\Controllers\Dashboard\DashboardController::class, 'trend'])->name('dashboard.trend');
    Route::get('/dashboard/consumption', [\App\Http\Controllers\Dashboard\DashboardController::class, 'consumption'])->name('dashboard.consumption');

    // Consumption insights page
    Route::get('/insights', [\App\Http\Controllers\Dashboard\DashboardController::class, 'insights'])->name('insights');

    // Receipt management routes
    Route::resource('receipts', \App\Http\Controllers\ReceiptController::class);
    Route::get('/categories', [\App\Http\Controllers\ReceiptController::class, 'categories'])->name('categories');
    Route::post('/receipts/{receipt}/retry', [\App\Http\Controllers\ReceiptController::class, 'retry'])->name('receipts.retry');
    Route::get('/receipts/{receipt}/file', [\App\Http\Controllers\ReceiptController::class, 'file'])->name('receipts.file');

    // Recurring expenses: providers & monthly contracts
    Route::resource('providers', \App\Http\Controllers\ProviderController::class)->except('show');
    Route::resource('contracts', \App\Http\Controllers\ContractController::class);
});

require __DIR__.'/auth.php';
