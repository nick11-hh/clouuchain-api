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
        //landlord
        Schema::create('dsp_after_sales_work_order', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('custom_id')->default(0)->comment('客户id');
            $table->string('work_order_id')->comment('工单号');
            $table->string('reference_order_id')->default('')->comment('参考单号');
            $table->string('desc', 500)->default('')->comment('问题描述');
            $table->tinyInteger('type')->default(1)->comment('工单类型 1包裹丢失、2包裹破损、3货物不对、4更换商品、5其他类型');
            $table->string('attachment_url')->default('')->comment('上传的附件URL地址');
            $table->bigInteger('claim_admin_id')->default(0)->comment('认领管理员id(即处理人id)');
            $table->string('handle_result', 500)->default('')->comment('处理结果');
            $table->tinyInteger('status')->default(1)->comment('状态 1待处理 2处理中 3已处理 4已关闭');

            $table->timestamp('handle_time')->nullable()->comment('处理时间');
            $table->timestamps();

            $table->index(['custom_id']);
            $table->index(['work_order_id']);
            $table->index(['reference_order_id']);
        });

        DB::statement("ALTER TABLE `dsp_after_sales_work_order` COMMENT '售后工单表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_after_sales_work_order');
    }
};
