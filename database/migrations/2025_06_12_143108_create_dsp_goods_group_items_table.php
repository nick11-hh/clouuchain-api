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
        Schema::create('dsp_goods_group_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('group_sku_id')->comment('组合商品sku id');
            $table->bigInteger('goods_sku_id')->comment('组合的基础商品sku id');
            $table->integer('quantity')->comment('组合商品数量');
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
        Schema::dropIfExists('dsp_goods_group_items');
    }
};
