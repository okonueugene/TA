<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $user = User::create([
            'name' => 'Adminnistrator',
            'email' => 'admin@admin.com',
            'password' => Hash::make('12345678'),
            'user_type' => 'admin',

        ]);

        $user1 = User::create([
            'name' => 'Employee',
            'email' => 'employee@employee.com',
            'password' => Hash::make('12345678'),
            'user_type' => 'employee',
        ]);

        $user2 = User::create([
            'name' => 'Manager',
            'email' => 'manager@manager.com',
            'password' => Hash::make('12345678'),
            'user_type' => 'manager',
        ]);

        $user3 = User::create([
            'name' => 'General Manager',
            'email' => 'generalmanager@gmail.com',
            'password' => Hash::make('12345678'),
            'user_type' => 'general_manager',
        ]);
    }
}
