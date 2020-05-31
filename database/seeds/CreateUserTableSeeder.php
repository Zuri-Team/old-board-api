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

        // factory('App\User', 3990)->create();

        /** @var \App\User $user */
        $superadmin = User::create([
            'firstname' => 'The',
            'lastname' => 'Adming',
            'username' => 'hngi7bot',
            'email' => 'admin@hng.tech',
            'stack' => '',
            'location' => '',
	        'slack_id' => '',
	        'gender' => 'Male',
            'password' => bcrypt('hngPass321'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        $superadmin->assignRole('superadmin');

    }
}
