<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@hollal.local'],
            [
                'name' => 'Super Admin',
                'phone' => '0500000000',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $user->syncRoles(['Super Admin']);
    }
}
