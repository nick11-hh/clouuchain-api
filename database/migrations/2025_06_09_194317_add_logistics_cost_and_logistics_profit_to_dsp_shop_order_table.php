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
            $table->tinyInteger('freight_quote_calculate_method')->nullable()->comment('物流利润计算方式 1按百分比计算 2按固定金额计算');
            $table->decimal('logistics_cost', 10, 2)->nullable()->default(0)->comment('物流成本');
            $table->decimal('logistics_profit', 10, 2)->nullable()->default(0)->comment('物流利润');
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
            $table->dropColumn('freight_quote_calculate_method');
            $table->dropColumn('logistics_cost');
            $table->dropColumn('logistics_profit');
        });
    }
};
