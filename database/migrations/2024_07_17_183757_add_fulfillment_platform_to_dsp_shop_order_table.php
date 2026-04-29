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
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->string('fulfillment_platform')->nullable()->comment('履约平台');
            $table->tinyInteger('fulfillment_push_status')->default(0)->comment('履约推送状态');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->dropColumn('fulfillment_platform');
            $table->dropColumn('fulfillment_push_status');
        });
    }
};
