<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        User::updateOrCreate(
            ['email' => 'admin@tireslot.local'],
            ['name' => 'Администратор', 'password' => $password, 'role' => RoleEnum::Admin],
        );

        User::updateOrCreate(
            ['email' => 'operator@tireslot.local'],
            ['name' => 'Оператор', 'password' => $password, 'role' => RoleEnum::Operator],
        );
    }
}
