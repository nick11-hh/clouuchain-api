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
            $table->decimal('favourable_price', 10)->default(0)->comment('优惠价格');
            $table->decimal('sku_logistics_fee', 10)->default(0)->comment('sku物流报价 订单一口价时保存');
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
            $table->dropColumn([
                'favourable_price',
                'sku_logistics_fee',
            ]);
        });
    }
};
