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
            $table->decimal('purchase_price')->nullable()->comment('采购价格');
            $table->integer('receive_quantity')->default(0)->comment('到货数量');
            $table->integer('send_quantity')->default(0)->comment('发走数量');
            $table->integer('inbound_quantity')->default(0)->comment('入库数量');
            $table->json('order_sn')->nullable()->comment('关联的订单编号');
            $table->json('plan_sn')->nullable()->comment('关联的采购计划');
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
            $table->dropColumn('purchase_price');
            $table->dropColumn('receive_quantity');
            $table->dropColumn('inbound_quantity');
            $table->dropColumn('order_sn');
            $table->dropColumn('plan_sn');
        });
    }
};
