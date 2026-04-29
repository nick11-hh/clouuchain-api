<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('dsp_third_party_warehouse_configs', function (Blueprint $table) {
            $table->tinyInteger('mark_in_distribution_after_push_order')->default(0)->comment('推送订单后是否标记配货中')->after('app_secret');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_third_party_warehouse_configs', function (Blueprint $table) {
            $table->dropColumn('mark_in_distribution_after_push_order');
        });
    }
};
