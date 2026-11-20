<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // First admin account. Email + password come from .env (ADMIN_EMAIL / ADMIN_PASSWORD).
        $password = env('ADMIN_PASSWORD') ?: Str::password(16);

        // firstOrCreate: re-running the seeder never resets a password changed in the admin.
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@fashion-store.test')],
            ['name' => 'Store Admin', 'password' => $password, 'role' => 'admin'],
        );

        $this->call([CatalogueSeeder::class, ContentSeeder::class, ShippingSeeder::class]);
    }
}
