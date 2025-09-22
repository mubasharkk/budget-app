<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Receipt management routes
    Route::resource('receipts', \App\Http\Controllers\ReceiptController::class);
    Route::get('/categories', [\App\Http\Controllers\ReceiptController::class, 'categories'])->name('categories');
    Route::post('/receipts/{receipt}/retry', [\App\Http\Controllers\ReceiptController::class, 'retry'])->name('receipts.retry');
    Route::get('/receipts/{receipt}/file', [\App\Http\Controllers\ReceiptController::class, 'file'])->name('receipts.file');
});

require __DIR__.'/auth.php';
