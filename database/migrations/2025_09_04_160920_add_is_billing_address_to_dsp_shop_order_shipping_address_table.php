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
        Schema::table('dsp_shop_order_shipping_address', function (Blueprint $table) {
            $table->tinyInteger('is_billing_address')->default(0)->comment('是否使用的是账单地址');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order_shipping_address', function (Blueprint $table) {
            $table->dropColumn('is_billing_address');
        });
    }
};
