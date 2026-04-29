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
        Schema::create('dsp_recharge_applies', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->comment('客户id')->index();
            $table->integer('payment_type_id')->comment('支付方式id')->index();
            $table->bigInteger('apply_amount')->comment('申请充值金额（分）');
            $table->json('apply_images')->nullable()->comment('申请审核图片');
            $table->string('apply_remark')->nullable()->comment('客户备注');
            $table->string('pay_account')->nullable()->comment('支付账号');
            $table->bigInteger('confirm_amount')->nullable()->comment('确认金额（分）');
            $table->json('confirm_images')->nullable()->comment('确认金额');
            $table->string('confirm_remark')->nullable()->comment('审核备注');
            $table->tinyInteger('status')->default(0)->comment('0 待审核 1 审核通过 2 审核拒绝')->index();
            $table->string('serial_no')->comment('交易编号');
            $table->string('out_serial_no')->comment('外部交易编号');
            $table->integer('apply_operator')->nullable()->comment('申请操作员');
            $table->integer('confirm_operator')->nullable()->comment('审核操作员');
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
        Schema::dropIfExists('dsp_recharge_applies');
    }
};
