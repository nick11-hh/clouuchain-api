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
            $table->bigInteger('goods_sku_id')->nullable()->comment('报价时的商品本地sku id');
            $table->decimal('quote_price', 10)->nullable()->comment('报价金额');
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
            $table->dropColumn('goods_sku_id');
            $table->dropColumn('quote_price');
        });
    }
};
