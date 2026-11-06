<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Users ──────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@rental.com'],
            [
                'name'     => 'Admin Rental',
                'email'    => 'admin@rental.com',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'user@rental.com'],
            [
                'name'     => 'User Demo',
                'email'    => 'user@rental.com',
                'password' => Hash::make('password'),
                'role'     => 'user',
            ]
        );

        // ── Katalog ────────────────────────────────────────
        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}
