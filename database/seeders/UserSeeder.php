<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'name' => 'admin',
            'email' => 'admin'.'@admin.com',
            'password' => Hash::make('password'),
            'role' => 'admin'
        ]);

        DB::table('users')->insert([
            'name' => 'superuser',
            'email' => 'superuser'.'@superuser.com',
            'password' => Hash::make('9056683492'),
            'role' => 'admin'
        ]);

        DB::table('users')->insert([
            'name' => 'moderator',
            'email' => 'moderator'.'@moderator.com',
            'password' => Hash::make('password'),
            'role' => 'educator'
        ]);

        DB::table('users')->insert([
            'name' => 'user1',
            'email' => 'user1'.'@user.com',
            'password' => Hash::make('password'),
        ]);
    }
}
