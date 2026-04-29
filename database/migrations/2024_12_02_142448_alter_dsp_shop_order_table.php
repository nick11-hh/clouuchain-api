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
            $table->string('platform_order_status',50)->default('')->comment('平台订单状态');
            $table->string('platform_payment_status',50)->default('')->comment('平台支付状态');
            $table->string('platform_fulfillment_status',50)->default('')->comment('平台发货状态');
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
            $table->dropColumn([
                'platform_order_status',
                'platform_payment_status',
                'platform_fulfillment_status',
            ]);
        });
    }
};
