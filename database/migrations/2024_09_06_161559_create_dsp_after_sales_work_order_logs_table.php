<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_after_sales_work_order_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('work_order_id')->default(0)->comment('工单id');
            $table->bigInteger('custom_id')->default(0)->comment('客户id');
            $table->tinyInteger('type')->default(1)->comment('变动类型 1创建 2认领 3处理 4关闭');
            $table->string('content')->default('')->comment('内容');
            $table->bigInteger('handle_id')->default(0)->comment('处理人id');
            $table->timestamps();

            $table->index(['work_order_id']);
            $table->index(['custom_id']);
            $table->index(['handle_id']);
        });

        DB::statement("ALTER TABLE `dsp_after_sales_work_order_logs` COMMENT '售后工单处理日志'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_after_sales_work_order_logs');
    }
};
