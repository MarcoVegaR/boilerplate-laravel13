<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    return $request->user()
        ? to_route('dashboard')
        : to_route('login');
})->name('home');

Route::middleware(['auth', 'verified', 'ensure-two-factor'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/system.php';
