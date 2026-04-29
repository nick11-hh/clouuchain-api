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
        Schema::create('dsp_stock_process_log', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('stock_up_id')->comment('备货id');
            $table->unsignedTinyInteger('node')->comment('节点：1=流程发起，2=企微审批，3=发起采购，4=财务核账');
            $table->string('approve_name')->nullable()->comment('审批人姓名');
            $table->string('approval_opinion')->nullable()->comment('审批意见');
            $table->string('supervisor')->comment('负责人');
            $table->unsignedInteger('submitter_id')->comment('发起人id');
            $table->tinyInteger('process')->comment('当前流程状态:1=审批中，2=已通过，3=已驳回，4=已取消，5=财务核账，6=完成，7=转审');
            $table->dateTime('created_at')->comment('创建时间');
            $table->dateTime('deleted_at')->nullable()->comment('删除时间');
            $table->comment('备货流程日志表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_stock_process_log');
    }
};
