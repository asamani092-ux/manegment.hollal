<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo onboarding data — run manually: php artisan db:seed --class=OnboardingSeeder
 * NOT registered in DatabaseSeeder.
 */
class OnboardingSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_INITIAL_PASSWORD', '12341234');

        $users = [
            [
                'phone' => '0501111111',
                'name' => 'أحمد المدير العام',
                'email' => 'gm@hollal.demo',
                'role' => 'General Manager',
            ],
            [
                'phone' => '0502222222',
                'name' => 'سارة المديرة التنفيذية',
                'email' => 'em@hollal.demo',
                'role' => 'Executive Manager',
            ],
            [
                'phone' => '0503333333',
                'name' => 'خالد مدير المشاريع',
                'email' => 'pm@hollal.demo',
                'role' => 'Project Manager',
            ],
            [
                'phone' => '0504444444',
                'name' => 'نورة المالية',
                'email' => 'finance@hollal.demo',
                'role' => 'Finance',
            ],
            [
                'phone' => '0505555555',
                'name' => 'محمد الموظف',
                'email' => 'employee@hollal.demo',
                'role' => 'Employee',
            ],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::updateOrCreate(
                ['phone' => $data['phone']],
                [
                    ...$data,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'must_change_password' => true,
                ]
            );

            $user->syncRoles([$role]);
        }
    }
}
