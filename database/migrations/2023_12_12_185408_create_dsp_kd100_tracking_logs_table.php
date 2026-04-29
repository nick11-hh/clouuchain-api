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
        Schema::create('dsp_kd100_tracking_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->tinyInteger('type')->index('jiyun_kd100_tracking_logs_type_index')->comment('类型1-包裹2-订单');
            $table->string('express_num', 191)->index('jiyun_kd100_tracking_logs_express_num_index')->comment('快递单号');
            $table->string('status', 191)->nullable()->default('')->comment('状态');
            $table->string('description', 191)->nullable()->default('')->comment('描述');
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
        Schema::dropIfExists('dsp_kd100_tracking_logs');
    }
};
