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
        Schema::create('dsp_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_sn')->default('')->comment('采购单号');
            $table->integer('shop_order_id')->default(0)->comment('关联订单id');
            $table->integer('shop_id')->default(0)->comment('店铺id');
            $table->string('platform_sn')->default('')->comment('采购平台订单号');
            $table->string('shipment_number')->default('')->comment('物流单号');
            $table->tinyInteger('status')->default(0)->comment('采购状态：0-待处理 1-已采购 2-待入库 3-已入库 4-已发货 5-已取消');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index('order_sn');
            $table->index('shop_order_id');
            $table->index(['platform_sn', 'shipment_number']);
        });

        Schema::create('dsp_purchase_orders_items', function (Blueprint $table) {
            $table->id();
            $table->integer('purchase_order_id')->default(0)->comment('采购单id');
            $table->text('platform_url')->comment('采购平台链接');
            $table->text('imgs')->comment('商品图片');
            $table->string('title')->default('')->comment('商品名称');
            $table->string('variant_title')->default('')->comment('商品规格');
            $table->integer('quantity')->default(0)->comment('采购数量');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_purchase_orders');
        Schema::dropIfExists('dsp_purchase_orders_items');
    }
};
