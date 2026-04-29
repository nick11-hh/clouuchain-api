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
        Schema::table('dsp_purchase_plan_items', function (Blueprint $table) {
            $table->integer('supplier_id')->default(0)->comment('供应商id');
            $table->integer('shop_id')->default(0)->comment('店铺id');
            $table->integer('warehouse_id')->default(0)->comment('仓库id');
            $table->renameColumn('item_sn', 'sn');
            $table->renameColumn('item_image', 'images');
            $table->renameColumn('item_name', 'goods_name');
            $table->renameColumn('item_spec_name', 'spec_name');
            $table->renameColumn('purchase_time', 'plan_procurement_time');
            $table->renameColumn('quantity', 'plan_qty');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_purchase_plan_items', function (Blueprint $table) {
            $table->dropColumn(['supplier_id', 'shop_id', 'warehouse_id']);
            $table->renameColumn('sn', 'item_sn');
            $table->renameColumn('images', 'item_image');
            $table->renameColumn('goods_name', 'item_name');
            $table->renameColumn('spec_name', 'item_spec_name');
            $table->renameColumn('plan_procurement_time', 'purchase_time');
            $table->renameColumn('plan_qty', 'quantity');
        });
    }
};
