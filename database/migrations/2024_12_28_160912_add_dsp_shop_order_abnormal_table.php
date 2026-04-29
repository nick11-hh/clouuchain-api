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
        Schema::create('dsp_shop_order_abnormal', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->comment("订单id");
            $table->string('abnormal_reason', 100)->comment('异常原因');
            $table->text('description')->nullable()->comment('描述');
            $table->bigInteger('operator_id')->default(0)->comment('异常操作人员 0 为系统');
            $table->timestamp('abnormal_time')->nullable()->comment('异常时间');
            $table->tinyInteger('deal_status')->default(1)->comment('处理状态 1 待处理 2 已处理');
            $table->string('deal_type')->nullable()->comment('处理类型');
            $table->timestamp('deal_time')->nullable()->comment('处理时间');
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
        Schema::dropIfExists('dsp_shop_order_abnormal');
    }
};
