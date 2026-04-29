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
        Schema::table('dsp_order_third_party_fulfillment_logs', function (Blueprint $table) {
            $table->string('platform_order_no')->after('order_id')->nullable()->comment('订单平台编号');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_order_third_party_fulfillment_logs', function (Blueprint $table) {
            $table->dropColumn('platform_order_no');
        });
    }
};
