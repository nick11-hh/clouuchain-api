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
        Schema::create('dsp_commission_withdraws', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->comment('客户id');
            $table->decimal('withdraw_amount')->comment('提现金额');
            $table->integer('withdraw_type')->comment('收款方式');
            $table->string('withdraw_account')->nullable()->comment('收款账号');
            $table->string('custom_remark')->nullable()->comment('客户备注');
            $table->json('custom_images')->nullable()->comment('客户图片');
            $table->decimal('confirm_amount')->nullable()->comment('确认金额');
            $table->string('confirm_remark')->nullable()->comment('审核备注');
            $table->json('confirm_images')->nullable()->comment('审核图片');
            $table->integer('confirm_operator')->nullable()->comment('审核员工');
            $table->tinyInteger('status')->default(1)->comment('提现申请状态  1 待审核 2 已审核 3 未通过');
            $table->tinyInteger('withdraw_status')->default(1)->comment('提现打款状态  1 待提现 2 提现成功');
            $table->string('serial_no')->nullable()->comment('流水号');
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
        Schema::dropIfExists('dsp_commission_withdraws');
    }
};
