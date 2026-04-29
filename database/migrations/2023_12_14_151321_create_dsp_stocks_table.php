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
        Schema::create('dsp_stocks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('warehouse_id')->comment('仓库id');
            $table->bigInteger('custom_id')->default(0)->comment('客户id');
            $table->bigInteger('goods_id')->nullable()->comment('商品id');
            $table->string('goods_name')->comment('商品名称');
            $table->string('sku_id')->nullable()->comment('sku_id');
            $table->string('spec_name')->comment('规格名称');
            $table->string('sku')->comment('sku 编号');
            $table->integer('total_quantity')->comment('总库存数量');
            $table->integer('quantity')->comment('可用库存数量');
            $table->integer('lock_quantity')->default(0)->comment('锁定库存数量');
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
        Schema::dropIfExists('dsp_stocks');
    }
};
