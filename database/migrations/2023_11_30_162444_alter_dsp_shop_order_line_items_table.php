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
            $table->string('platform')->nullable()->comment('采购平台');
            $table->string('sku')->nullable()->comment('sku编码');
            $table->string('offerId')->nullable()->comment('商品id');
            $table->string('specId')->nullable()->comment('商品规格id');
            $table->string('alibaba_order_id')->default('')->comment('1688平台采购订单id');
            $table->index('purchase_order_id');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_purchase_orders_items', function (Blueprint $table) {
            $table->dropColumn(['platform', 'sku', 'offerId', 'specId', 'alibaba_order_id']);
        });
    }
};
