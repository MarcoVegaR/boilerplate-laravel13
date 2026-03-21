<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('database seeder creates and updates a single local administrator', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'test@mailinator.com')->sole();

    expect(User::query()->where('email', 'test@mailinator.com')->count())->toBe(1)
        ->and($admin->name)->toBe('Administrador')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('12345678', $admin->password))->toBeTrue();
});

test('database seeder keeps deterministic administrator scoped to development environments', function () {
    config(['app.env' => 'production']);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'test@mailinator.com')->count())->toBe(0);
});

test('database seeder creates expected super-admin role and 7 baseline permissions', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::where('name', 'super-admin')->exists())->toBeTrue()
        ->and(Permission::count())->toBe(7);
});

test('repeated database seeder run preserves exactly 1 role and 7 permissions without duplicates', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Role::where('name', 'super-admin')->count())->toBe(1)
        ->and(Permission::count())->toBe(7);
});
