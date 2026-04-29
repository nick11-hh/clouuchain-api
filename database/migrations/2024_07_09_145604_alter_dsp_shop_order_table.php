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
            $table->tinyInteger('order_status_before')->default(0)->comment('前一个订单状态');
            $table->tinyInteger('is_shipping')->default(0)->comment('是否发货：0-否 1是');
            $table->integer('staff_id')->default(0)->comment('员工ID');
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
                'order_status_before',
                'is_shipping',
                'staff_id',
                               ]);
        });
    }
};
