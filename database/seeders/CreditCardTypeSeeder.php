<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CreditCardTypeSeeder extends Seeder
{
    /**
     * 信用卡配置表
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('credit_card_types')->insert([
            [
                'id' => 1,
                'name' => '40Seas',
                'client_id' => null,
                'client_secret' => null,
                'webhook_secret' => null,
                'minimum_payment' => '1.00',
                'service_charge_rate' => '0.00',
                'service_charge_amount' => '0.00',
                'status' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
