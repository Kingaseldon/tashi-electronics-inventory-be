<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserRoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //admin/super admin

        $developerRole = Role::create([
            'name' => 'Developer',
            'guard_name' => 'api',
            'description' => 'TICL Developers',
            'is_super_user' => 1
        ]);
        
        //administrator user
        $user = new User();
        $user->name = 'Developer';
        $user->username = 'developer';
        $user->email = 'developer@gmail.com';
        $user->password = Hash::make('password');
        $user->designation = 'TICL Developer';
        $user->save();
        //assign role to the user
        $user->assignRole($developerRole);

        Role::create([
            'name' => 'TashiElectronic Admin',
            'guard_name' => 'api',
            'description' => 'TashiElectronic Admin',
            'is_super_user' => 1
        ]);


        //insert all the roles in the system
        $roles = array(
            // array('name' => 'TashiElectronic Admin', 'is_super_user' => 1,  'guard_name' => 'api', 'description' => 'IT personal or related'),
            array('name' => 'Executive Manager', 'guard_name' => 'api', 'description' => 'Executive manager to view the important menus'),
            array('name' => 'Manager', 'guard_name' => 'api', 'description' => 'Managers'),
            array('name' => 'Accountant', 'guard_name' => 'api', 'description' => 'Accountant of the reginal/extension'),
            array('name' => 'Customer Care Executives', 'guard_name' => 'api', 'description' => 'Customer Care Executives regional/extension'),
        );
        Role::insert($roles);
    }
}
