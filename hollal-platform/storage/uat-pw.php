<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$admin = User::where('phone', '0500000000')->first();

if (! $admin) {
    echo 'admin=MISSING'.PHP_EOL;

    return;
}

echo 'admin='.$admin->name.'|active='.(int) $admin->is_active.'|status='.$admin->employment_status.PHP_EOL;
echo 'env_password_matches='.(Hash::check((string) env('ADMIN_INITIAL_PASSWORD'), $admin->password) ? 'yes' : 'no').PHP_EOL;
