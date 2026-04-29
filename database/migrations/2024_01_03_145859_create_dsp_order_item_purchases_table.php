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
        Schema::create('dps_order_item_purchases', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->comment('订单id');
            $table->bigInteger('order_item_id')->comment('订单 item id');
            $table->bigInteger('purchase_id')->comment('采购订单id');
            $table->bigInteger('purchase_item_id')->comment('采购订单 item id');
            $table->integer('quantity')->comment('采购数量');
            $table->tinyInteger('status')->default(0)->comment('0 未到货 1 已到货');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::dropIfExists('purchase_item_sources');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dps_order_item_purchases');
    }
};
