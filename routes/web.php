<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SavingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/income', [ProfileController::class, 'updateIncome'])->name('profile.income.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Dashboard routes
    Route::get('/dashboard/chart/data', [DashboardController::class, 'chartData'])->name('dashboard.chart.data');
    Route::get('/dashboard/categories', [DashboardController::class, 'categories'])->name('dashboard.categories');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/dashboard/spending-by-category', [DashboardController::class, 'spendingByCategory'])->name('dashboard.spending.by.category');
    Route::get('/dashboard/overview', [DashboardController::class, 'overview'])->name('dashboard.overview');
    Route::get('/dashboard/trend', [DashboardController::class, 'trend'])->name('dashboard.trend');
    Route::get('/dashboard/consumption', [DashboardController::class, 'consumption'])->name('dashboard.consumption');
    Route::get('/dashboard/deals', [DashboardController::class, 'dealsData'])->name('dashboard.deals');
    Route::get('/dashboard/budgets', [DashboardController::class, 'budgets'])->name('dashboard.budgets');
    Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot'])->name('dashboard.snapshot');

    // Consumption insights page
    Route::get('/insights', [DashboardController::class, 'insights'])->name('insights');
    Route::get('/deals', [DashboardController::class, 'deals'])->name('deals');

    // Product price intelligence detail
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/data', [ProductController::class, 'data'])->name('products.data');

    // Receipt management routes
    Route::get('/receipts/scan', [ReceiptController::class, 'scan'])->name('receipts.scan');
    Route::resource('receipts', ReceiptController::class);
    Route::get('/categories', [ReceiptController::class, 'categories'])->name('categories');
    Route::post('/receipts/{receipt}/retry', [ReceiptController::class, 'retry'])->name('receipts.retry');
    Route::get('/receipts/{receipt}/file', [ReceiptController::class, 'file'])->name('receipts.file');

    // Recurring expenses: providers & monthly contracts
    Route::resource('providers', ProviderController::class)->except('show');
    Route::resource('contracts', ContractController::class);
    Route::post('/contracts/{contract}/mark-paid', [ContractController::class, 'markPaid'])
        ->name('contracts.mark-paid');

    // Recurring monthly income (stored on the user) + one-time income entries
    Route::patch('/incomes/monthly', [IncomeController::class, 'updateMonthly'])
        ->name('incomes.monthly.update');
    Route::resource('incomes', IncomeController::class)->except('show');

    // Manual savings entries
    Route::resource('savings', SavingController::class)->except('show');

    // Category budgets
    Route::resource('budgets', BudgetController::class)->except('show');

    // Budgeting agent
    Route::get('/agent', [AgentController::class, 'index'])->name('agent');
    Route::get('/dashboard/agent', [AgentController::class, 'dashboard'])->name('dashboard.agent');
    Route::post('/agent/ask', [AgentController::class, 'ask'])->name('agent.ask');
    Route::get('/agent/history', [AgentController::class, 'history'])->name('agent.history');
    Route::delete('/agent/history', [AgentController::class, 'clearHistory'])->name('agent.history.clear');
    Route::get('/agent/mentionables', [AgentController::class, 'mentionables'])->name('agent.mentionables');
    Route::post('/agent/digest', [AgentController::class, 'generateDigest'])->name('agent.digest');
});

require __DIR__.'/auth.php';
