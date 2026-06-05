<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the admin user for local development and first access.
     */
    public function run(): void
    {
        $password = env('ADMIN_USER_PASSWORD');

        if (! $password) {
            throw new \RuntimeException('Set ADMIN_USER_PASSWORD before running AdminUserSeeder.');
        }

        User::updateOrCreate(
            ['email' => env('ADMIN_USER_EMAIL', 'admin@goudendraak.local')],
            [
                'name' => env('ADMIN_USER_NAME', 'Admin'),
                'password' => $password,
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );
    }
}
