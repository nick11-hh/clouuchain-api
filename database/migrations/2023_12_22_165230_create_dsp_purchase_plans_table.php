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
        Schema::create('dsp_purchase_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_sn')->comment('计划编号');
            $table->tinyInteger('type')->comment('采购计划类型 1 订单采购单 2 自定义采购单');
            $table->tinyInteger('status')->comment('采购计划状态');
            $table->bigInteger('create_user_id')->default(0)->comment('采购计划创建人id');
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
        Schema::dropIfExists('dsp_purchase_plans');
    }
};
