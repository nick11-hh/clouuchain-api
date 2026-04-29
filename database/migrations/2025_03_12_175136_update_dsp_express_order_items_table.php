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
        Schema::table('dsp_express_order_items', function (Blueprint $table) {
            $table->bigInteger('shop_order_id')->default(0)->comment('dsp_shop_order.id');
            $table->bigInteger('shop_order_item_id')->default(0)->comment('dsp_shop_order_line_items.id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_express_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'shop_order_id',
                'shop_order_item_id'
            ]);
        });
    }
};
