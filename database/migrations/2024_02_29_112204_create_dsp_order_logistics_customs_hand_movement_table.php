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
        Schema::create('dsp_order_logistics_customs_hand_movement', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id')->default(0)->comment('订单id');
            $table->string('cn_name')->default('')->comment('中文名称');
            $table->string('en_name')->default('')->comment('英文名称');
            $table->decimal('unit_price', 10)->default(0)->comment('报关单价');
            $table->decimal('weight', 10, 3)->default(0)->comment('报关重量');
            $table->string('material')->default('')->comment('材质');
            $table->string('use_to')->default('')->comment('用途');
            $table->string('code')->default('')->comment('海关编码');
            $table->string('attributes')->default('')->comment('物品属性');
            $table->index('order_id');
            $table->softDeletes();
            $table->timestamps();
            $table->comment('手动报关信息表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_order_logistics_customs_hand_movement');
    }
};
