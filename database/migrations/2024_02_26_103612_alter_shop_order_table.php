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
            $table->string('order_id')->default('')->comment('第三方平台订单id')->change();
            $table->decimal('current_subtotal_price', 12)->default(0)->comment('税前金额')->change();
            $table->decimal('current_total_discounts', 12)->default(0)->comment('折扣金额')->change();
            $table->decimal('current_total_price', 12)->default(0)->comment('合计金额')->change();
            $table->decimal('current_total_tax', 12)->default(0)->comment('税金金额')->change();
            $table->integer('shop_id')->default(0)->comment('shop.id')->change();
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
            $table->bigInteger('order_id')->default(0)->comment('第三方平台订单id')->change();
            $table->decimal('current_subtotal_price', 10)->comment('税前金额')->change();
            $table->decimal('current_total_discounts', 10)->comment('折扣金额')->change();
            $table->decimal('current_total_price', 10)->comment('合计金额')->change();
            $table->decimal('current_total_tax', 10)->comment('税金金额')->change();
            $table->integer('shop_id')->comment('shop.id')->change();
        });
    }
};
