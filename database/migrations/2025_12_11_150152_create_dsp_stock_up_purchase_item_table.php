<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDspStockUpPurchaseItemTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_stock_up_purchase_item', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('stock_up_id')->comment('备货id');
            $table->string('order_num')->comment('订单编号');
            $table->string('sku')->comment('sku');
            $table->unsignedInteger('num')->default('0')->comment('件数');
            $table->decimal('total', 12, 2)->comment('总价(CNY)');
            $table->string('warehouse')->comment('仓库名');
            $table->dateTime('created_at')->comment('创建时间');
            $table->dateTime('deleted_at')->nullable()->comment('删除时间');
            $table->comment('备货采购详情表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_stock_up_purchase_item');
    }
}
