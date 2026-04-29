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
        Schema::create('dsp_work_order_logs', function (Blueprint $table) {
            $table->comment('工单日志表');
            $table->bigIncrements('id');
            $table->bigInteger('main_id')->default(0)->index('dsp_work_order_logs_main_id_index')->comment('主工单ID');
            $table->bigInteger('child_id')->nullable()->index('dsp_work_order_logs_child_id_index')->comment('子工单ID');
            $table->integer('type')->comment('日志类型(0-新建工单 1-指派 2-新建子工单 3-子工单完成 4-工单完成 5-工单激活 6-工单关闭)');
            $table->string('content', 191)->comment('日志内容');
            $table->bigInteger('assigned_by')->nullable()->comment('指派人id');
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
        Schema::dropIfExists('dsp_work_order_logs');
    }
};
