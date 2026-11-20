<?php

use Glubschi\Seat\Contracts\Http\Controllers\ContractsController;
use Illuminate\Support\Facades\Route;

// Middleware as used by SeAT core and plugins: web session, logged in, the user's language,
// and the plugin permission. Route names are scoped "seatcontracts::" (SeAT development tips).
Route::group([
    'prefix' => 'contracts',
    'middleware' => ['web', 'auth', 'locale', 'can:contracts.view'],
], function () {
    Route::get('/', [ContractsController::class, 'index'])->name('seatcontracts::home');
    Route::get('/hull/{typeId}', [ContractsController::class, 'hull'])->whereNumber('typeId')->name('seatcontracts::hull');
    Route::get('/items/{contractId}', [ContractsController::class, 'items'])->whereNumber('contractId')->name('seatcontracts::items');
    Route::post('/open-window', [ContractsController::class, 'openWindow'])->name('seatcontracts::open-window');
});
