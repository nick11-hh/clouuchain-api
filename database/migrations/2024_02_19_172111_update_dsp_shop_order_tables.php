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
            $table->dropColumn('admin_graphql_api_id');
            $table->dropColumn('app_id');
            $table->dropColumn('browser_ip');
            $table->dropColumn('buyer_accepts_marketing');
            $table->dropColumn('closed_at');
            $table->dropColumn('cart_token');
            $table->dropColumn('checkout_id');
            $table->dropColumn('checkout_token');
            $table->dropColumn('confirmed');
            $table->dropColumn('contact_email');
            $table->dropColumn('customer_locale');
            $table->dropColumn('email');
            $table->dropColumn('estimated_taxes');
            $table->dropColumn('financial_status');
            $table->dropColumn('fulfillment_status');
            $table->dropColumn('gateway');
            $table->dropColumn('name');
            $table->dropColumn('note');
            $table->dropColumn('number');
            $table->dropColumn('order_number');
            $table->dropColumn('phone');
            $table->dropColumn('presentment_currency');
            $table->dropColumn('processed_at');
            $table->dropColumn('processing_method');
            $table->dropColumn('reference');
            $table->dropColumn('referring_site');
            $table->dropColumn('source_identifier');
            $table->dropColumn('source_name');
            $table->dropColumn('source_url');
            $table->dropColumn('tags');
            $table->dropColumn('taxes_included');
            $table->dropColumn('test');
            $table->dropColumn('token');
            $table->dropColumn('total_discounts');
            $table->dropColumn('total_line_items_price');
            $table->dropColumn('total_outstanding');
            $table->dropColumn('total_price');
            $table->dropColumn('total_price_usd');
            $table->dropColumn('total_tax');
            $table->dropColumn('total_tip_received');
            $table->dropColumn('total_weight');
            $table->dropColumn('customer');
            $table->dropColumn('client_details');
            $table->dropColumn('payment_details');
            $table->dropColumn('billing_address');
            $table->dropColumn('shipping_address');
            $table->dropColumn('shipping_lines');
            $table->dropColumn('refunds');
            $table->dropColumn('shipment_number');
            $table->dropColumn('face_order_failed');
            $table->dropColumn('po_number');
            $table->string('order_id')->change();
            $table->string('platform')->after('order_id')->default('shopify')->comment('订单所属平台');
            $table->json('payment_info')->after('order_id')->nullable()->comment('支付详情');
        });
        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
           $table->dropColumn('admin_graphql_api_id');
           $table->dropColumn('fulfillable_quantity');
           $table->dropColumn('fulfillment_service');
           $table->dropColumn('fulfillment_status');
           $table->dropColumn('gift_card');
           $table->dropColumn('grams');
           $table->dropColumn('product_exists');
           $table->dropColumn('requires_shipping');
           $table->dropColumn('vendor');
           $table->dropColumn('variant_inventory_management');
           $table->dropColumn('taxable');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
