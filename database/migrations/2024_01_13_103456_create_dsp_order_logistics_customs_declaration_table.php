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
        Schema::create('dsp_order_logistics_customs_declaration', function (Blueprint $table) {
            $table->id();
            $table->integer('order_item_id')->default(0)->comment('订单商品id');
            $table->string('cn_name')->default('')->comment('报关中文名');
            $table->string('en_name')->default('')->comment('报关英文名');
            $table->decimal('unit_price', 10, 2)->default(0)->comment('报关单价');
            $table->string('code')->default('')->comment('海关编码');
            $table->bigInteger('weight')->default(0)->comment('报关重量(g)');
            $table->string('address')->default('')->comment('发货地址');
            $table->string('attributes')->default('')->comment('物品属性');
            $table->string('material')->default('')->comment('材质');
            $table->string('use_to')->default('')->comment('用途');
            $table->comment('订单商品报关信息表');
            $table->index('order_item_id');

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
        Schema::dropIfExists('dsp_order_logistics_customs_declaration');
    }
};
