<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDspStockUpProductItemTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_stock_up_product_item', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('stock_up_id')->comment('备货id');
            $table->string('sku')->comment('sku');
            $table->unsignedInteger('num')->default('0')->comment('件数');
            $table->decimal('price', 12, 2)->comment('单价(CNY)');
            $table->decimal('total', 12, 2)->comment('总价(CNY)');
            $table->dateTime('created_at')->comment('创建时间');
            $table->dateTime('deleted_at')->nullable()->comment('删除时间');
            $table->comment('备货产品详情表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_stock_up_product_item');
    }
}
