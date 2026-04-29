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
            $table->tinyInteger('order_type')->default(1)->comment('订单类型 1-代发订单 2-备货订单');
            $table->integer('warehouse_id')->default(0)->comment('仓库ID');
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
                'goods_type',
                'warehouse_id',
            ]);
        });
    }
};
