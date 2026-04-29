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
        Schema::create('dsp_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('支付方式名称');
            $table->string('pay_logo')->comment('支付方式logo图标');
            $table->string('pay_qrcode')->nullable()->comment('支付二维码');
            $table->string('pay_account')->nullable()->comment('支付账号');
            $table->string('remark')->nullable()->comment('备注信息');
            $table->tinyInteger('enabled')->comment('是否开启  1 开启 0 关闭');
            $table->string('currency')->comment('币种');
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
        Schema::dropIfExists('dsp_payment_settings');
    }
};
