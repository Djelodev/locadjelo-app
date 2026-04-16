<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);

        DB::table('users')->insert([
            [
                'role_id'       => 1,
                'name'          => 'Admin',
                'username'      => 'admin',
                'email'         => 'admin@mail.com',
                'image'         => 'default.png',
                'about'         => 'Bio of admin',
                'password'      => bcrypt('codeastro.com'),
                'created_at'    => date("Y-m-d H:i:s")
            ],
            [
                'role_id'       => 2,
                'name'          => 'Agent CodeAstro',
                'username'      => 'agent',
                'email'         => 'agent@mail.com',
                'image'         => 'default.png',
                'about'         => '',
                'password'      => bcrypt('codeastro.com'),
                'created_at'    => date("Y-m-d H:i:s")
            ],
            [
                'role_id'       => 3,
                'name'          => 'User Demo',
                'username'      => 'user',
                'email'         => 'user@mail.com',
                'image'         => 'default.png',
                'about'         => null,
                'password'      => bcrypt('codeastro.com'),
                'created_at'    => date("Y-m-d H:i:s")
            ],
        ]);


        DB::table('roles')->    insert([
            [
                'name'          => 'Admin',
                'slug'          => 'admin',
                'created_at'    => date("Y-m-d H:i:s")
            ],
            [
                'name'          => 'Agent',
                'slug'          => 'agent',
                'created_at'    => date("Y-m-d H:i:s")
            ],
            [
                'name'          => 'User',
                'slug'          => 'user',
                'created_at'    => date("Y-m-d H:i:s")
            ]
        ]);

    }   
}   
