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
        Schema::create('dsp_balance_recharges', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custom_id')->comment('客户id');
            $table->tinyInteger('type')->comment('支付类型 1 paypal');
            $table->string('out_trade_no')->comment('外部交易单号');
            $table->string('trade_no')->nullable()->comment('内部交易单号');
            $table->decimal('recharge_amount', 10)->comment('充值金额');
            $table->decimal('pay_amount', 10)->nullable()->comment('支付金额');
            $table->string('currency')->comment('币种');
            $table->timestamp('notify_time')->nullable()->comment('支付回调时间');
            $table->tinyInteger('status')->default(0)->comment('支付状态，0 未支付 1 支付成功');
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
        Schema::dropIfExists('dsp_balance_recharges');
    }
};
