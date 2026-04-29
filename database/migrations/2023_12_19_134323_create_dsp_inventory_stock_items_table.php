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
        Schema::create('dsp_inventory_stock_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('warehouse_id')->comment('仓库id');
            $table->bigInteger('inventory_id')->comment('盘点单id');
            $table->bigInteger('stock_id')->comment('盘点单id');
            $table->bigInteger('stock_item_id')->comment('盘点单id');
            $table->bigInteger('location_id')->comment('库位id');
            $table->string('location_code')->comment('库位code');
            $table->integer('origin_quantity')->comment('原库存');
            $table->integer('actual_quantity')->comment('实盘库存');
            $table->integer('diff_quantity')->comment('差量');
            $table->string('remark')->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_inventory_stock_items');
    }
};
