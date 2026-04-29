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
        Schema::create('dsp_companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 50)->nullable()->comment('公司名');
            $table->string('phone', 50)->comment('手机号码');
            $table->string('email', 100)->comment('邮箱');
            $table->bigInteger('company_group_id')->comment('公司组ID');
            $table->dateTime('last_login_at')->nullable()->comment('最后登录时间');
            $table->dateTime('contract_end_at')->nullable()->comment('合同截止日期');
            $table->unsignedTinyInteger('require_audit')->default(1)->comment('需要审核');
            $table->char('uuid', 36)->comment('UUID');
            $table->boolean('is_v3')->default(false)->comment('是否V3版本');
            $table->string('customer_name', 191)->nullable()->default('')->comment('客服名称');
            $table->string('declare_tax_number', 191)->nullable()->default('')->comment('申报订单默认税号');
            $table->string('declare_currency', 191)->nullable()->default('')->comment('申报订单默认货币');
            $table->string('declare_unit', 191)->nullable()->default('')->comment('申报订单默认单位');
            $table->string('contract_name', 191)->default('')->comment('合同名称');
            $table->string('contract_number', 191)->default('')->comment('合同编号');
            $table->double('contract_amount')->default(0)->comment('合同金额');
            $table->date('contract_created_at')->nullable()->comment('合同开始时间');
            $table->tinyInteger('simplify')->nullable()->default(0)->comment('精简版0-否1-是');
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
        Schema::dropIfExists('dsp_companies');
    }
};
