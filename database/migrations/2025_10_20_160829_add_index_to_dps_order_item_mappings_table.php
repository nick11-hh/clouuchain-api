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
        Schema::table('dps_order_item_mappings', function (Blueprint $table) {
            $table->index('platform_variant_id');
            $table->index('goods_sku_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dps_order_item_mappings', function (Blueprint $table) {
            $table->dropIndex('dps_order_item_mappings_platform_variant_id');
            $table->dropIndex('dps_order_item_mappings_goods_sku_id');
        });
    }
};
