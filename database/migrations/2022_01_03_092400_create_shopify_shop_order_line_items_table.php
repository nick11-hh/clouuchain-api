<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShopifyShopOrderLineItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('admin_graphql_api_id')->nullable()->comment('GRAPHQL');
            $table->integer('fulfillable_quantity')->nullable()->comment('fulfillable_quantity');
            $table->string('fulfillment_service')->nullable()->comment('fulfillment_service');
            $table->string('fulfillment_status')->nullable()->comment('fulfillment_status');

            $table->boolean('gift_card')->nullable()->comment('是否使用礼品卡');

            $table->integer('grams')->nullable()->comment('规格毛重');

            $table->string('name')->nullable()->comment('商品名称');
            $table->decimal('price', 10,2)->nullable()->comment('商品价格');
            $table->boolean('product_exists')->nullable()->comment('product_exists');

            $table->string('product_id')->nullable()->comment('商品ID');

            $table->integer('quantity')->comment('数量');

            $table->boolean('requires_shipping')->comment('是否需要邮寄');
            $table->string('sku')->nullable()->comment('SKU');
            $table->boolean('taxable')->comment('taxable');
            $table->string('title')->comment('title');
            $table->decimal('total_discount', 10, 2)->comment('总折扣金额');
            $table->string('variant_id')->comment('variant id');
            $table->string('variant_inventory_management')->comment('库存管理方');
            $table->string('variant_title')->nullable()->comment('库存管理方');
            $table->string('vendor')->nullable()->comment('供应商');
            $table->string('line_item_id')->nullable()->default('');
            $table->longText('imgs')->nullable()->comment('商品图片');
            $table->timestamp('deleted_at')->nullable();
            $table->index('name');
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
        Schema::dropIfExists('dsp_shop_order_line_items');
    }
}
