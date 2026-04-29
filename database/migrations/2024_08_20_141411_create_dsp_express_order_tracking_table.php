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
        Schema::create('dsp_express_order_tracking', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('express_order_id')->index()->comment('物流订单ID');
            $table->string('platform')->nullable()->comment('轨迹平台 17track');
            $table->string('tracking_number')->nullable()->comment('物流跟踪号');
            $table->string('carrier_code')->nullable()->comment('运输商代码');
            $table->integer('status')->default(0)->comment('物流状态: 0-未查询 1-查询不到 2-等待揽件');
            $table->string('tracking_status')->nullable()->comment('原始物流状态');
            $table->string('tracking_sub_status')->nullable()->comment('原始物流子状态');
            $table->text('remark')->nullable()->comment('物流轨迹备注');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('物流订单轨迹表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_order_tracking');
    }
};
