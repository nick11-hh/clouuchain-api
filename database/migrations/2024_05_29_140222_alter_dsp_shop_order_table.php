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
            $table->decimal('other_supplement_price', 10, 2)->default(0)->comment('其他补价')->after('vendor_change_price');
            $table->tinyInteger('order_one_price')->default(0)->comment('订单一口价: 1-是 2-否 0-未设置过一口价');
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
                'other_supplement_price',
                'order_one_price',
            ]);
        });
    }
};
