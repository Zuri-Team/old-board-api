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
            'firstname' => 'Admin',
            'lastname' => 'One',
            'username' => '@admin',
            'email' => 'admin@start.ng',
            'stack' => '',
            'location' => 'Lagos, Nigeria',
	        'slack_id' => '',
	        'gender' => 'Male',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);
        // $role = Role::findByName('superadmin');
        // RoleUser::create([
        //     'role_id' => $role->id,
        //     'user_id' => $superadmin->id
        // ]);
        $superadmin->assignRole('superadmin');
        
        $intern = User::create([

            'firstname' => 'Intern',

            'lastname' => 'One',

            'username' => '@intern',

            'email' => 'intern@start.ng',

            'stack' => '',

            'location' => 'Lagos, Nigeria',

	        'slack_id' => '',

	        'gender' => 'Male',

            'password' => bcrypt('password'),

            'role' => 'intern',

            'email_verified_at' => now(),

        ]);

        // $role = Role::findByName('intern');

        // RoleUser::create([

        //     'role_id' => $role->id,

        //     'user_id' => $superadmin->id

        // ]);

        $intern->assignRole('intern');
        
    }
}
