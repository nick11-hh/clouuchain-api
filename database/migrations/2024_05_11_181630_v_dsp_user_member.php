<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_dsp_user_member` AS select `um`.`user_id` AS `user_id`,(select `ml`.`id` from `dsp_member_level` `ml` where (`ml`.`growth_value` <= `um`.`growth_value`) order by `ml`.`growth_value` desc limit 1) AS `level_id`,`um`.`growth_value` AS `growth_value`,`um`.`point` AS `point` from `dsp_user_member` `um`');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('DROP VIEW IF EXISTS v_dsp_user_member');
    }
};
