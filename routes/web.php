<?php

use App\Http\Controllers\System\UserRoleAssignmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

// Permission-protected system routes — enforces backend authorization contract.
// Each route calls Gate::authorize(), returning 403 for insufficient permission.
Route::middleware(['auth', 'verified', 'ensure-two-factor'])->prefix('system')->name('system.')->group(function () {
    Route::get('users', function (Request $request) {
        Gate::authorize('system.users.view');

        return response()->json(['users' => []]);
    })->name('users.index');

    Route::patch('users/{user}/role', UserRoleAssignmentController::class)
        ->name('users.role.assign');
});

require __DIR__.'/settings.php';
