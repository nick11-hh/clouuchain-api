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
        Schema::create('dsp_express_order_items', function (Blueprint $table) {
            $table->id();
            $table->integer('express_order_id')->default(0)->comment('物流订单ID')->index();
            $table->string('cn_name')->default('')->comment('报关中文名');
            $table->string('en_name')->default('')->comment('报关英文名');
            $table->decimal('unit_price', 10)->default(0)->comment('报关单价(USD)');
            $table->bigInteger('weight')->default(0)->comment('报关重量(g)');
            $table->string('hs_code')->default('')->comment('海关编码');
            $table->string('attributes')->default('')->comment('物品属性');
            $table->integer('quantity')->default(1)->comment('数量');
            $table->string('material')->default('')->comment('材质');
            $table->string('use_to')->default('')->comment('用途');
            $table->string('sku')->default('')->comment('sku');

            $table->timestamps();
            $table->softDeletes();
            $table->comment('物流订单报关信息表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_order_items');
    }
};
