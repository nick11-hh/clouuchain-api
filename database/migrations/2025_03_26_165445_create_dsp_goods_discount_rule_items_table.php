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
        Schema::create('dsp_goods_discount_rule_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('rule_id')->comment('规则id');
            $table->bigInteger('goods_id')->comment('商品id');
            $table->integer('quantity')->comment('商品数量');
            $table->timestamps();
            $table->softDeletes();
            $table->index('rule_id');
            $table->index('goods_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_goods_discount_rule_items');
    }
};
