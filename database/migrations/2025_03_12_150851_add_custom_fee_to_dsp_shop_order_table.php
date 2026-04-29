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
            $table->decimal('logistics_compute_fee', 10)->default(0)->comment('报价计算的物流费用');
            $table->decimal('favourable_compute_price', 10)->default(0)->comment('报价计算的优惠金额');
            $table->decimal('total_price', 10)->default(0)->comment('报价总金额');
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
            $table->dropColumn('logistics_compute_fee');
            $table->dropColumn('favourable_compute_price');
            $table->dropColumn('total_price');
        });
    }
};
