<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        User::query()->updateOrCreate([
            'email' => 'test@mailinator.com',
        ], [
            'name' => 'Administrador',
            'password' => '12345678',
            'email_verified_at' => now(),
        ]);
    }
}
