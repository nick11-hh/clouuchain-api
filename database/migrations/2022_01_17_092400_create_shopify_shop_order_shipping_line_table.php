<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShopifyShopOrderShippingLineTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_shop_order_shipping_line', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('carrier_identifier')->nullable()->comment('第三方提供服务');
            $table->string('code')->nullable()->comment('快递代码');
            $table->string('delivery_category')->nullable()->comment('配货方式');
            $table->string('discounted_price')->nullable()->comment('已应用折扣的税前运费。');
            $table->string('phone')->nullable()->comment('送货地址的电话号码。');
            $table->string('price')->nullable()->comment('城市');
            $table->string('requested_fulfillment_service_id')->nullable()->comment('为运输方式请求的履行服务');
            $table->string('source')->nullable()->comment('来源');
            $table->string('title')->nullable()->comment('快递名称');
            $table->json('tax_lines')->nullable();
            $table->json('discount_allocations')->nullable();
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
        Schema::dropIfExists('dsp_shop_order_shipping_line');
    }
}
