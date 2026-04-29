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
        Schema::create('dsp_shipment_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('shipment_id')->comment('发货单ID');
            $table->bigInteger('order_id')->comment('订单ID');
            $table->string('order_sn', 191)->comment('订单号');
            $table->timestamps();
            $table->bigInteger('company_id')->comment('公司ID');

            $table->index(['shipment_id', 'order_id', 'company_id'], 'shipment_order_company_id_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shipment_orders');
    }
};
