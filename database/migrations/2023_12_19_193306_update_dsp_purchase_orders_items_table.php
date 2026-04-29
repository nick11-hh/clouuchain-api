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
        Schema::table('dsp_purchase_orders_items', function (Blueprint $table) {
            $table->bigInteger('goods_id')->default(0)->comment('商品id');
            $table->bigInteger('sku_id')->default(0)->comment('sku id');
        });
    }

    /**
     * Reverse the migrationa'pis.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_purchase_orders_items', function (Blueprint $table) {
            $table->dropColumn('goods_id');
            $table->dropColumn('sku_id');
        });
    }
};
