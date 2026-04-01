<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles and permissions are seeded in all environments
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(AccessModulePermissionsSeeder::class);
        $this->call(AuditModulePermissionsSeeder::class);
        $this->call(AiCopilotPermissionsSeeder::class);

        if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $admin = User::query()->updateOrCreate([
            'email' => 'test@mailinator.com',
        ], [
            'name' => 'Administrador',
            'password' => '12345678',
            'email_verified_at' => now(),
        ]);

        // Assign super-admin role to local admin user (idempotent)
        $admin->assignRole('super-admin');

        // Test data seeders — separate from core seeders
        $this->call(TestRolesSeeder::class);
        $this->call(TestUsersSeeder::class);
    }
}
