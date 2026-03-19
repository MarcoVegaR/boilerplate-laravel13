<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

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
