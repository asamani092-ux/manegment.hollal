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

        $user = User::query()->where('email', 'admin@hollal.local')->first();
        if (! $user) {
            $user = User::query()->create([
                'name' => 'Super Admin',
                'email' => 'admin@hollal.local',
                'phone' => '0500000000',
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => true,
            ]);
        }

        $user->syncRoles(['Super Admin']);

        if ($generated) {
            $this->command?->warn('Admin user created/updated. Initial password (save it now): '.$password);
        }
    }
}
