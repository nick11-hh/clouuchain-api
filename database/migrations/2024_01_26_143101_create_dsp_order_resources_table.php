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
        Schema::create('dsp_order_resources', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id')->default(0)->comment('客户id');
            $table->json('imgs')->nullable()->comment('图片');
            $table->string('product_name')->default('')->comment('产品名称');
            $table->string('url')->default('')->comment('产品链接');
            $table->decimal('target_price', 10)->default(0)->comment('目标价格');
            $table->tinyInteger('status')->default(0)->comment('状态：0-待认领 1-报价中 2-等待确认 3-报价成功 4-拒绝报价');
            $table->decimal('price', 10)->default(0)->comment('报价价格');
            $table->integer('purchaser')->default(0)->comment('采购员id');
            $table->string('remark')->default('')->comment('备注');
            $table->string('desc')->default('')->comment('详情描述');
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
        Schema::dropIfExists('dsp_order_resources');
    }
};
