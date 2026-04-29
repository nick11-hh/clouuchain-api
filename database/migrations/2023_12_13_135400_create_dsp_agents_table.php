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
        Schema::create('dsp_agents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('agent_id')->index('dsp_agents_agent_id_index')->comment('代理  id');
            $table->string('agent_name')->comment('代理名称');
            $table->string('contact_name')->comment('代理联系人');
            $table->string('contact_phone')->comment('代理联系人电话');
            $table->string('contact_email')->comment('代理联系人邮箱');
            $table->string('wechat_id', 191)->default('')->comment('微信号');
            $table->mediumInteger('commission')->comment('佣金分成比例');
            $table->string('remark')->comment('备注');
            $table->mediumText('qr_code')->nullable()->comment('代理对应的小程序二维码');
            $table->tinyInteger('should_notify')->default(1)->comment('是否需要弹窗通知');
            $table->string('qr_code_url', 191)->nullable()->comment('代理二维码链接');
            $table->string('promote_url', 191)->default('')->comment('推广链接');
            $table->unsignedTinyInteger('enabled')->default(1)->comment('是否启用');
            $table->unsignedTinyInteger('mode')->default(0)->comment('计算模式 0 支付金额 1 实际运费金额');
            $table->unsignedTinyInteger('type')->default(1)->comment('佣金计算方式');
            $table->bigInteger('template_id')->default(0);
            $table->tinyInteger('is_bind')->nullable()->default(0)->comment('是否绑定1-是0-否');
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
            $table->unsignedBigInteger('level')->default(1)->comment('代理佣金等级');
            $table->string('bind_fail_remark', 191)->nullable()->default('')->comment('绑定失败备注');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_agents');
    }
};
