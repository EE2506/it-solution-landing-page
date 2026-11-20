<?php

use App\Http\Controllers\OwnerController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [QuoteController::class, 'index']);
Route::post('/estimate', [QuoteController::class, 'preview'])->middleware('throttle:90,1');
Route::post('/quotes', [QuoteController::class, 'store'])->middleware('throttle:10,1')->block();

Route::prefix('owner')->group(function (): void {
    Route::view('/sign-in', 'owner.login')->middleware('guest')->name('login');
    Route::post('/sign-in', [OwnerController::class, 'signIn'])->middleware(['guest', 'throttle:5,1'])->name('owner.login');
    Route::post('/sign-out', [OwnerController::class, 'signOut'])->middleware('auth')->name('owner.logout');

    Route::middleware(['auth', 'can:manage-quotes', 'cache.headers:private;no_store'])->name('owner.')->group(function (): void {
        Route::get('/', [OwnerController::class, 'index'])->name('index');
        Route::post('/search', [OwnerController::class, 'search'])->name('search');
        Route::get('/quotes/{quote}', [OwnerController::class, 'show'])->name('show');
        Route::patch('/quotes/{quote}', [OwnerController::class, 'update'])->name('update');
        Route::post('/quotes/{quote}/delivery/{delivery}/retry', [OwnerController::class, 'retry'])->name('retry');
    });
});
