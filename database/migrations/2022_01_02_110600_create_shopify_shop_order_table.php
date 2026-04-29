<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShopifyShopOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('dsp_shop_order', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id')->default(0)->comment('客户id');
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('admin_graphql_api_id')->nullable()->comment('GRAPHQL');
            $table->integer('app_id')->nullable()->comment('APP ID');
            $table->string('browser_ip')->nullable()->comment('浏览器IP');
            $table->boolean('buyer_accepts_marketing')->default(0)->comment('接收广告推送');
            $table->string('cancel_reason', 10)->nullable()->comment('取消理由');
            $table->timestamp('cancelled_at')->nullable()->comment('取消时间');
            $table->string('cart_token')->nullable()->comment('cart token');
            $table->string('checkout_id')->nullable()->comment('cart token');
            $table->string('checkout_token')->nullable()->comment('cart token');

            $table->timestamp('closed_at')->nullable()->comment('cart token');
            $table->boolean('confirmed')->comment('确认');
            $table->string('contact_email')->comment('联系EMAIL');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->string('currency', 3)->nullable()->comment('实付货币');
            $table->decimal('current_subtotal_price', 10, 2)->comment('税前金额');
            $table->decimal('current_total_discounts', 10, 2)->comment('折扣金额');
            $table->decimal('current_total_price', 10, 2)->comment('合计金额');
            $table->decimal('current_total_tax', 10, 2)->comment('税金金额');
            $table->string('customer_locale', 10)->nullable()->comment('当前结算语言');

            $table->string('email')->nullable()->comment('email');
            $table->boolean('estimated_taxes')->comment('是否估算税金');
            $table->string('financial_status', 10)->comment('支付状态：pending-待处理  paid-已付款  partially_paid-部分付款  refunded-已退款  voided-作废  partially_refunded-部分退款');
            $table->string('fulfillment_status', 10)->nullable()->comment('履行状态：shipped-已发货 partial-部分发货 unshipped-未发货');
            $table->string('gateway', 10)->comment('支付网关');

            $table->string('name')->comment('规格名称');
            $table->string('note')->nullable()->comment('店主备注');
            $table->integer('number')->comment('订单在列表中的编号');
            $table->integer('order_number')->comment('订单在列表中的编号');
            $table->string('phone')->nullable()->comment('线上交易快照');
            $table->string('presentment_currency', 3)->nullable()->comment('线上交易快照');
            $table->timestamp('processed_at')->nullable()->comment('处理时间');
            $table->string('processing_method',10 )->nullable()->comment('处理时间');


            $table->string('reference')->nullable()->comment('来源');
            $table->string('referring_site')->nullable()->comment('referring_site');

            $table->string('source_identifier')->nullable()->comment('来源');
            $table->string('source_name')->nullable()->comment('来源');
            $table->string('source_url')->nullable()->comment('source url');

            $table->decimal('subtotal_price', 10, 2)->nullable()->comment('小计');
            $table->string('tags')->nullable()->comment('tags');
            $table->boolean('taxes_included')->nullable()->comment('是否含税');
            $table->boolean('test')->nullable()->comment('是否测试订单');
            $table->string('token')->comment('token');
            $table->decimal('total_discounts', 10, 2)->nullable()->comment('总折扣');
            $table->decimal('total_line_items_price', 10, 2)->nullable()->comment('订单总和');
            $table->decimal('total_outstanding', 10, 2)->nullable()->comment('订单总和');
            $table->decimal('total_price', 10, 2)->nullable()->comment('订单总和');
            $table->decimal('total_price_usd', 10, 2)->nullable()->comment('订单总和');
            $table->decimal('total_tax', 10, 2)->nullable()->comment('税金总和');
            $table->decimal('total_tip_received', 10, 2)->nullable()->comment('小费');
            $table->integer('total_weight')->nullable()->comment('总重量');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->timestamp('deleted_at')->nullable();

            $table->integer('shop_id')->comment('shopify.shop.id');
            $table->json('customer')->nullable();
            $table->json('client_details')->nullable();
            $table->json('payment_details')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('shipping_lines')->nullable();
            $table->json('refunds')->nullable();
            $table->index('shop_id');
            // $table->tinyInteger('quote_status')->default(0)->comment('报价状态：0-未报价 1-请求报价 2-已报价');
            // $table->tinyInteger('payment_status')->default(0)->comment('支付状态：0-待支付 1-已支付');
            $table->tinyInteger('order_status')->default(0)->comment('订单状态：0-未报价 1-请求报价 2-待支付 3-待处理 4-申请运单号 5-已交运 6-交运成功 7-交运失败 8-已取消 9-运单号申请成功 10-运单号申请失败');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('yunliantiao_shop_order');
    }
}
