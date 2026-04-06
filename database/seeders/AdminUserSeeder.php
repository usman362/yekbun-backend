<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@yekbun.com'],
            [
                'name'           => 'Super Admin',
                'email'          => 'admin@yekbun.com',
                'password'       => Hash::make('123456'),
                'is_admin_user'  => 1,
                'is_superadmin'  => 1,
                'status'         => 1,
                'level'          => 'superadmin',
                'user_type'      => 'admin',
            ]
        );
    }
}
