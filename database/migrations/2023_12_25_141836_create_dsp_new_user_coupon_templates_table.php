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
        Schema::create('dsp_new_user_coupon_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('coupon_type')->default(1)->comment('类型 1 金额券 2 重量券');
            $table->json('name');
            $table->bigInteger('config_id');
            $table->bigInteger('amount');
            $table->bigInteger('threshold');
            $table->timestamp('begin_at')->nullable();
            $table->timestamp('end_at')->nullable()->comment('结束时间');
            $table->unsignedInteger('days')->default(1)->comment('有效时长');
            $table->json('express_line_ids')->nullable();
            $table->bigInteger('order_amount');
            $table->unsignedTinyInteger('type')->default(1);
            $table->bigInteger('max_coupon_amount');
            $table->unsignedTinyInteger('times')->default(1);
            $table->tinyInteger('trigger_condition')->nullable()->comment('触发条件1-新用户下单第一笔2-新用户第一次登陆');
            $table->bigInteger('weight')->default(0)->comment('重量');
            $table->bigInteger('min_weight')->default(0)->comment('最小重量');
            $table->integer('count')->default(1)->comment('发放数量');
            $table->text('remark')->nullable()->comment('备注');
            $table->integer('event')->default(1)->comment('事件类型');
            $table->integer('discount_method')->default(0)->comment('抵扣金额类型');
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
        Schema::dropIfExists('dsp_new_user_coupon_templates');
    }
};
