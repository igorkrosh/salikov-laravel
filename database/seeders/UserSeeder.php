<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use DateTime;
use DateTimeZone;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('Europe/Moscow'));
        $dt->setTimestamp(time());

        $emailVerifiedAt = $dt->format('Y-m-d H:i:s');

        DB::table('users')->insert([
            'name' => 'admin',
            'email' => 'admin'.'@admin.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => $emailVerifiedAt
        ]);

        DB::table('users')->insert([
            'name' => 'superuser',
            'email' => 'superuser'.'@superuser.com',
            'password' => Hash::make('9056683492'),
            'role' => 'admin',
            'email_verified_at' => $emailVerifiedAt
        ]);

        DB::table('users')->insert([
            'name' => 'moderator',
            'email' => 'moderator'.'@moderator.com',
            'password' => Hash::make('password'),
            'role' => 'educator',
            'email_verified_at' => $emailVerifiedAt
        ]);

        DB::table('users')->insert([
            'name' => 'user1',
            'email' => 'user1'.'@user.com',
            'password' => Hash::make('password'),
            'email_verified_at' => $emailVerifiedAt
        ]);
    }
}
