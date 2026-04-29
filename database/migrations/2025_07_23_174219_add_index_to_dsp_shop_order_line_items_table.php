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
        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->index('variant_id');
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
        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->dropIndex('variant_id');
            $table->dropIndex('goods_sku_id');
        });
    }
};
