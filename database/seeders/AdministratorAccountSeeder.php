<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdministratorAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('dsp_admins')->insert([
            'name'        => 'admin',
            'username'    => 'admin',
            'password'    => '$2y$10$zfFYAVJYZP90Pg3p.QJKC.10ERTZjsbyFHZ//UbPlnuMSEUbpykkq',
            'group_id'    => 1,
            'super_admin' => 1,
            'created_at'  => now(),
            'updated_at'  => now()
        ]);

        DB::table('dsp_admin_groups')->insert([
            'id'          => 1,
            'name'        => '超级管理员',
            'description' => '超级管理员用户组',
            'created_at'  => now(),
            'updated_at'  => now()
        ]);
    }
}
