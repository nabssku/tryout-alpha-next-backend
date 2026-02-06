<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'user@mail.com'],
            [
                'name' => 'User Test',
                'password' => Hash::make('password'),
                'role' => 'user',
            ]
        );
    }
}
