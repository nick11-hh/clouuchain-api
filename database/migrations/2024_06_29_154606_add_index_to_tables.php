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
            $table->index('order_status');
        });

        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->index('sku');
        });

        Schema::table('dsp_inbound_orders', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('dsp_inbound_order_items', function (Blueprint $table) {
            $table->index('inbound_id');
        });

        Schema::table('dsp_outbound_orders', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('dsp_outbound_order_items', function (Blueprint $table) {
            $table->index('outbound_id');
        });

        Schema::table('dsp_outbound_order_addresses', function (Blueprint $table) {
            $table->index('outbound_id');
        });

        Schema::table('dsp_picking_orders', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('dsp_purchase_orders', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('dsp_express_line_regions', function (Blueprint $table) {
            $table->index('express_line_id');
        });

        Schema::table('dsp_express_line_price_rules', function (Blueprint $table) {
            $table->index('region_id');
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
            $table->dropIndex('order_status');
        });

        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->dropIndex('sku');
        });

        Schema::table('dsp_inbound_orders', function (Blueprint $table) {
            $table->dropIndex('status');
        });

        Schema::table('dsp_inbound_order_items', function (Blueprint $table) {
            $table->dropIndex('inbound_id');
        });

        Schema::table('dsp_outbound_orders', function (Blueprint $table) {
            $table->dropIndex('status');
        });

        Schema::table('dsp_outbound_order_items', function (Blueprint $table) {
            $table->dropIndex('outbound_id');
        });

        Schema::table('dsp_outbound_order_addresses', function (Blueprint $table) {
            $table->dropIndex('outbound_id');
        });

        Schema::table('dsp_picking_orders', function (Blueprint $table) {
            $table->dropIndex('status');
        });

        Schema::table('dsp_purchase_orders', function (Blueprint $table) {
            $table->dropIndex('status');
        });

        Schema::table('dsp_express_line_regions', function (Blueprint $table) {
            $table->dropIndex('express_line_id');
        });

        Schema::table('dsp_express_line_price_rules', function (Blueprint $table) {
            $table->dropIndex('region_id');
        });
    }
};
