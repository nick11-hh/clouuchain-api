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
            $table->string('logistics_provider')->default('')->comment('物流商');
            $table->string('shipment_number')->default('')->comment('物流单号');
            $table->string('face_order_failed')->default('')->comment('面单申请失败原因');
            $table->string('po_number')->default('')->comment('关联采购订单号');
            $table->tinyInteger('sku_status')->default(0)->comment('sku状态：0-单sku单数 1-单sku多数 2-多sku');
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
            $table->dropColumn(['logistics_provider', 'shipment_number', 'face_order_failed', 'po_number', 'sku_status']);
        });
    }
};
