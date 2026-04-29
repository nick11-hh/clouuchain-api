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
        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->tinyInteger('add_type')->default(1)->comment('商品添加类型 1订单自动添加 2手动添加');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->dropColumn('add_type');
        });
    }
};
