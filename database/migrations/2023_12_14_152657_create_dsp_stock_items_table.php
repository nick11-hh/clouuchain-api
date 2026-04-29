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
        Schema::create('dsp_stock_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('stock_id')->comment('库存id');
            $table->bigInteger('custom_id')->default(0)->comment('所属客户id');
            $table->bigInteger('warehouse_id')->comment('仓库id');
            $table->integer('total_quantity')->comment('总库存数量');
            $table->integer('quantity')->comment('可用库存数量');
            $table->integer('lock_quantity')->default(0)->comment('锁定库存数量');
            $table->bigInteger('location_id')->comment('仓库货位id');
            $table->string('location_code')->comment('仓库货位编码');
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
        Schema::dropIfExists('dsp_stock_items');
    }
};
