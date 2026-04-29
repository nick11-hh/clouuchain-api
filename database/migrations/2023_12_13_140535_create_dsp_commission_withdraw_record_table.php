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
        Schema::create('dsp_commission_withdraw_record', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('amount')->comment('佣金提现记录金额');
            $table->bigInteger('confirm_amount')->default(0)->comment('确认提现金额');
            $table->tinyInteger('type')->comment('提现类型--预留  目前就是提现到余额');
            $table->tinyInteger('status')->comment('佣金提现记录状态 0 为待审核,1 为审核通过,2 为审核拒绝');
            $table->string('remark')->default('')->comment('备注 - 预留');
            $table->string('customer_remark', 160)->nullable()->comment('客服备注');
            $table->json('customer_images')->nullable()->comment('客服审核图片');
            $table->string('operator')->nullable()->comment('操作人');
            $table->bigInteger('user_id')->index('dsp_commission_withdraw_record_user_id_index')->comment('提现记录对应的用户 id');
            $table->string('account')->nullable()->default('');
            $table->string('serial_no')->nullable()->default('');
            $table->string('fullname', 100)->nullable()->default('')->comment('真实姓名');
            $table->string('idcard', 100)->nullable()->default('')->comment('身份证号');
            $table->string('bank_code', 100)->nullable()->default('')->comment('银行简码');
            $table->string('bank_name', 100)->nullable()->default('')->comment('银行名称');
            $table->string('bank_number', 100)->nullable()->default('')->comment('银行卡号');
            $table->string('phone', 100)->nullable()->default('')->comment('手机号');
            $table->string('email', 100)->nullable()->default('')->comment('电子邮件');
            $table->string('address', 200)->nullable()->default('')->comment('联系地址');
            $table->string('face_img', 300)->nullable()->default('')->comment('身份证正面url');
            $table->string('back_img', 300)->nullable()->default('')->comment('身份证反面url');
            $table->tinyInteger('withdraw_status')->nullable()->default(1)->comment('提现状态1-待提现2-提现中3-提现成功4-提现失败');
            $table->string('batch_no', 191)->nullable()->default('')->comment('提现批次号(第三方提现需要)');
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
        Schema::dropIfExists('dsp_commission_withdraw_record');
    }
};
