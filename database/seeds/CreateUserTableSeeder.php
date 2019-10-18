<?php

use Illuminate\Database\Seeder;
use App\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\RoleUser;

class CreateUserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');


        /** @var \App\User $user */
        $superadmin = User::create([
            'firstname' => 'Seyi',
            'lastname' => 'Onifade',
            'username' => '@xyluz',
            'email' => 'xyluz@hng.tech',
            'stack' => '',
            'location' => '',
            'password' => bcrypt('secret'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        // $role = Role::findByName('superadmin');

        // RoleUser::create([
        //     'role_id' => $role->id,
        //     'user_id' => $superadmin->id
        // ]);
        $superadmin->assignRole('superadmin');


    }
}