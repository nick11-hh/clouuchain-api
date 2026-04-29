<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDspStockUpTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_stock_up', function (Blueprint $table) {
            $table->increments('id');
            $table->string('stock_up_number')->comment('备货编号');
            $table->unsignedTinyInteger('stock_up_type')->comment('备货类型:1=国内仓全款备货，2=海外仓全款备货');
            $table->unsignedInteger('custom_id')->comment('客户id');
            $table->decimal('exchange_rate', 10, 2)->default('0.00')->comment('当前备货金额汇率(CNY转USD)');
            $table->decimal('stock_up_amount', 12, 2)->default('0.00')->comment('预计备货金额(USD)');
            $table->unsignedDecimal('credit_line', 12, 2)->default('0.00')->comment('信用额度');
            $table->unsignedDecimal('balance', 12, 2)->default('0.00')->comment('客户钱包金额');
            $table->unsignedDecimal('frozen_limit', 12, 2)->default('0.00')->comment('冻结额度');
            $table->unsignedDecimal('purchase_amount', 12, 2)->nullable()->default('0.00')->comment('实际采购金额(CNY)');
            $table->unsignedInteger('submitter_id')->comment('提交人id');
            $table->unsignedTinyInteger('process')->default('0')->comment('当前流程状态:0=暂存，1=审批中，2=已通过，3=已驳回，4=已取消，5=财务核账，6=完成');
            $table->text('stock_up_describe')->comment('备货描述');
            $table->string('purchase_opinion', 255)->nullable()->comment('采购意见');
            $table->string('sp_no', 255)->nullable()->comment('企微表单编号');
            $table->dateTime('created_at')->comment('创建时间');
            $table->dateTime('updated_at')->comment('更新时间');
            $table->dateTime('deleted_at')->nullable()->comment('删除时间');
            $table->comment('备货表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_stock_up');
    }
}
