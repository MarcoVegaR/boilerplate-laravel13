<?php

use App\Models\User;
use App\Support\PermissionName;
use Database\Seeders\RolesAndPermissionsSeeder;

test('baseline permissions satisfy the frozen naming convention', function () {
    foreach (RolesAndPermissionsSeeder::baselinePermissions() as $permission) {
        expect(PermissionName::isValid($permission))->toBeTrue();
    }
});

test('invalid permission names are rejected by the frozen naming convention', function (string $permission) {
    expect(fn () => PermissionName::assertValid($permission))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'uppercase segments' => 'System.Roles.View',
    'spaces' => 'system roles view',
    'ambiguous format' => 'manage-users',
]);

test('policy-backed role assignment endpoint denies underprivileged authenticated users', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->patch(route('system.users.role.assign', $target), ['role' => 'super-admin'])
        ->assertForbidden();

    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();
});

test('form request authorize allows super-admin to assign a role through the policy path', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $actor = User::factory()->withSuperAdmin()->withTwoFactor()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->patch(route('system.users.role.assign', $target), ['role' => 'super-admin'])
        ->assertSuccessful()
        ->assertJsonPath('role', 'super-admin');

    expect($target->fresh()->hasRole('super-admin'))->toBeTrue();
});

test('api-style unauthorized authorization failure returns json 403 instead of the inertia error page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->app['env'] = 'production';

    $actor = User::factory()->create();
    $target = User::factory()->create();
    $token = 'test-csrf-token';

    $this->actingAs($actor)
        ->withSession(['_token' => $token])
        ->patchJson(route('system.users.role.assign', $target), ['role' => 'super-admin'], ['X-CSRF-TOKEN' => $token])
        ->assertForbidden()
        ->assertJsonStructure(['message']);

    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();
});
