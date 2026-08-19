<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_INITIAL_PASSWORD', '12341234');
        $generated = false;

        if ($password === '') {
            $password = '12341234';
        }

        $user = User::updateOrCreate(
            ['email' => 'admin@hollal.local'],
            [
                'name' => 'Super Admin',
                'phone' => '0500000000',
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => true,
            ]
        );

        $user->syncRoles(['Super Admin']);

        if ($generated) {
            $this->command?->warn('Admin user created/updated. Initial password (save it now): '.$password);
        }
    }
}
