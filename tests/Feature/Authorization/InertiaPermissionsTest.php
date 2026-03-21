<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated super-admin has non-empty permissions array in inertia shared props', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Use local env to bypass 2FA enforcement middleware so we can reach the dashboard
    $this->app['env'] = 'local';

    $user = User::factory()->withSuperAdmin()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.permissions')
            ->where('auth.permissions', fn ($permissions) => count($permissions) === 7)
        );
});

test('authenticated super-admin inertia props contain all baseline permission names', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->app['env'] = 'local';

    $user = User::factory()->withSuperAdmin()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('system.roles.view')
                && collect($permissions)->contains('system.users.view')
                && collect($permissions)->contains('system.permissions.view')
            )
        );
});

test('authenticated user without any role has empty permissions array in inertia shared props', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', [])
        );
});

test('unauthenticated request to login page has empty permissions array in inertia shared props', function () {
    // No user is authenticated — the login page is an Inertia page shared by HandleInertiaRequests.
    // auth.permissions must be empty/absent so unauthenticated users receive no permission data.
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', [])
        );
});
