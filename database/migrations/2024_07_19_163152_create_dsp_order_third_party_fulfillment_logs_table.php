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
        Schema::create('dsp_order_third_party_fulfillment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->comment('订单id');
            $table->string('order_info')->comment('订单信息');
            $table->string('platform')->comment('推送平台');
            $table->string('status')->default(1)->comment('推送状态');
            $table->text('content')->nullable()->comment('推送日志内容');
            $table->bigInteger('operate_id')->nullable()->comment('操作人');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_order_third_party_fulfillment_logs');
    }
};
