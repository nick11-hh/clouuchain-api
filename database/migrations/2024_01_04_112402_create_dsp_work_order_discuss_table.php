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
        Schema::create('dsp_work_order_discuss', function (Blueprint $table) {
            $table->comment('工单沟通表');
            $table->bigIncrements('id');
            $table->bigInteger('main_id')->default(0)->index('dsp_work_order_discuss_main_id_index')->comment('主工单ID');
            $table->bigInteger('child_id')->nullable()->index('dsp_work_order_discuss_child_id_index')->comment('子工单ID');
            $table->bigInteger('evaluator_id')->default(0)->index('dsp_work_order_discuss_evaluator_id_index')->comment('评价人ID');
            $table->longText('content')->nullable()->comment('评价内容');
            $table->json('file')->nullable()->comment('附件');
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
        Schema::dropIfExists('dsp_work_order_discuss');
    }
};
