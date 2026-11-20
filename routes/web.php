<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\EstimateAccessController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [QuoteController::class, 'index']);
Route::post('/estimate', [QuoteController::class, 'preview'])->middleware('throttle:90,1,estimate-preview');
Route::post('/estimate/budget', [QuoteController::class, 'budget'])->middleware('throttle:20,1,estimate-budget');
Route::post('/quotes', [QuoteController::class, 'store'])->middleware('throttle:10,1,quote-submit')->block();

Route::middleware('cache.headers:private;no_store')->name('estimates.')->group(function (): void {
    Route::view('/find-estimate', 'estimates.find')->name('find');
    Route::post('/find-estimate', [EstimateAccessController::class, 'requestLink'])->middleware('throttle:5,1,estimate-lookup')->name('send');
    Route::get('/estimate-link/{quote}', [EstimateAccessController::class, 'open'])->middleware('signed')->name('open');
    Route::get('/estimates/{quote}', [EstimateAccessController::class, 'show'])->name('show');
    Route::get('/estimates/{quote}/pdf', [EstimateAccessController::class, 'pdf'])->middleware('throttle:15,1,estimate-pdf')->name('pdf');
    Route::get('/estimates/{quote}/revise', [QuoteController::class, 'revise'])->name('revise');
});

Route::prefix('owner')->group(function (): void {
    Route::view('/sign-in', 'owner.login')->middleware('guest')->name('login');
    Route::post('/sign-in', [OwnerController::class, 'signIn'])->middleware(['guest', 'throttle:5,1,owner-sign-in'])->name('owner.login');
    Route::post('/sign-out', [OwnerController::class, 'signOut'])->middleware('auth')->name('owner.logout');

    Route::middleware(['auth', 'can:manage-quotes', 'cache.headers:private;no_store'])->name('owner.')->group(function (): void {
        Route::get('/', [OwnerController::class, 'index'])->name('index');
        Route::post('/search', [OwnerController::class, 'search'])->name('search');
        Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog');
        Route::patch('/catalog/{group}/{item}', [CatalogController::class, 'update'])->name('catalog.update');
        Route::get('/quotes/{quote}', [OwnerController::class, 'show'])->name('show');
        Route::patch('/quotes/{quote}', [OwnerController::class, 'update'])->name('update');
        Route::post('/quotes/{quote}/delivery/{delivery}/retry', [OwnerController::class, 'retry'])->name('retry');
    });
});
