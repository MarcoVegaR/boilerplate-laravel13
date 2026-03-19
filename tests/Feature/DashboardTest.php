<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('dashboard uses spanish corporate copy and shared layout metadata', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('ui.locale', 'es')
            ->where('ui.branding.application', 'Boilerplate Caracoders')
            ->where('ui.branding.company', 'Caracoders Pro Services')
            ->where('ui.navigation.label', 'Navegación')
            ->where('ui.navigation.items.0.title', 'Panel')
            ->where('ui.navigation.starterPromoLinksRemoved', true)
            ->missing('dashboardContent'),
        );
});
