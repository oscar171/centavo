<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('accounts', AccountController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');

    Route::patch('transactions/{transaction}/category', [TransactionController::class, 'updateCategory'])
        ->name('transactions.category.update');

    Route::get('statements/create', [StatementController::class, 'create'])->name('statements.create');

    Route::post('accounts/{account}/statements', [StatementController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('statements.store');

    Route::post('statements/{statement}/reprocess', [StatementController::class, 'reprocess'])
        ->middleware('throttle:20,1')
        ->name('statements.reprocess');

    Route::get('statements/{statement}', [StatementController::class, 'show'])
        ->name('statements.show');
});

require __DIR__.'/settings.php';
