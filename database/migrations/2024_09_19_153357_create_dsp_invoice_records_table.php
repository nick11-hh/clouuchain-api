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
        Schema::create('dsp_invoice_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('custom_id')->default(0)->comment('客户id');
            $table->string('invoice_no')->default('')->comment('发票编号');
            $table->text('seller_info')->nullable()->comment('卖方信息');
            $table->text('buyer_info')->nullable()->comment('买方信息');
            $table->decimal('total_price', 10)->default(0)->comment('开票总金额');
            $table->string('storage_url')->default('')->comment('发票存储URL');
            $table->tinyInteger('source_type')->default(1)->comment('来源类型 1订单 2充值');
            $table->tinyInteger('status')->default(0)->comment('状态 1正常 2作废 0生成中 3生成失败');
            $table->timestamp('invalid_time')->nullable()->comment('作废时间');
            $table->timestamps();

            $table->index(['invoice_no']);
            $table->index(['custom_id']);
            $table->comment('发票记录表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_invoice_records');
    }
};
